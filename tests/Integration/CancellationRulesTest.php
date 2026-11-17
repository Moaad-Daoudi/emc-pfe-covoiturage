<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Domain\Enum\BookingStatus;
use App\Domain\Enum\TripStatus;
use App\Entity\Booking;
use App\Entity\TripSegment;
use App\Exception\CancellationNotAllowedException;
use App\Repository\BookingRepository;
use App\Repository\TripSegmentRepository;
use App\Service\CancellationService;
use App\Service\RideBookingManager;
use App\Tests\Support\CarpoolingTestHelperTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class CancellationRulesTest extends KernelTestCase
{
    use CarpoolingTestHelperTrait;

    private EntityManagerInterface $em;
    private RideBookingManager $bookingManager;
    private CancellationService $cancellationService;

    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test']);
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->em = $em;

        /** @var TripSegmentRepository $segmentRepo */
        $segmentRepo = $this->em->getRepository(TripSegment::class);
        /** @var BookingRepository $bookingRepo */
        $bookingRepo = $this->em->getRepository(Booking::class);

        $this->bookingManager = new RideBookingManager($this->em, $segmentRepo);
        $this->cancellationService = new CancellationService($this->em, $segmentRepo, $bookingRepo);

        $this->resetDatabase($this->em);
    }

    public function testPassengerCancellationOver24hRefunds100PercentAndRestoresSeats(): void
    {
        $driver = $this->createUser($this->em, 'driver@test.ma', 'Karim');
        $p1 = $this->createUser($this->em, 'p1@test.ma', 'Youssef');
        $p2 = $this->createUser($this->em, 'p2@test.ma', 'Salma');
        $vehicle = $this->createVehicle($this->em, $driver);
        $agadir = $this->createCity($this->em, 'Agadir');
        $marrakech = $this->createCity($this->em, 'Marrakech');

        $departureAt = new \DateTimeImmutable('+3 days');
        ['trip' => $trip, 'stops' => $stops] = $this->createMultiStopTrip(
            $this->em,
            $driver,
            $vehicle,
            [$agadir, $marrakech],
            $departureAt,
            seats: 1,
            segmentPrices: [100]
        );

        $booking = $this->bookingManager->book($p1, $trip, $stops[0], $stops[1], 1);
        self::assertSame(0, $this->bookingManager->getAvailableSeats($trip, $stops[0], $stops[1]));

        // Annulation 48h avant le départ (> 24h)
        $now = $departureAt->modify('-48 hours');
        $this->cancellationService->cancelByPassenger($booking, null, null, $now);

        self::assertSame(BookingStatus::CANCELLED_PASSENGER, $booking->getStatus());
        self::assertSame(100, $booking->getRefundAmount());
        self::assertSame(1, $this->bookingManager->getAvailableSeats($trip, $stops[0], $stops[1]));

        // La place libérée peut être revendue à P2
        $newBooking = $this->bookingManager->book($p2, $trip, $stops[0], $stops[1], 1);
        self::assertSame(BookingStatus::CONFIRMED, $newBooking->getStatus());
    }

    public function testPassengerCancellationUnder24hRefunds50Percent(): void
    {
        $driver = $this->createUser($this->em, 'driver@test.ma', 'Karim');
        $passenger = $this->createUser($this->em, 'p@test.ma', 'Youssef');
        $vehicle = $this->createVehicle($this->em, $driver);
        $agadir = $this->createCity($this->em, 'Agadir');
        $marrakech = $this->createCity($this->em, 'Marrakech');

        $departureAt = new \DateTimeImmutable('+2 days');
        ['trip' => $trip, 'stops' => $stops] = $this->createMultiStopTrip(
            $this->em,
            $driver,
            $vehicle,
            [$agadir, $marrakech],
            $departureAt,
            seats: 2,
            segmentPrices: [120]
        );

        $booking = $this->bookingManager->book($passenger, $trip, $stops[0], $stops[1], 1);

        // Annulation 12h avant le départ (< 24h)
        $this->cancellationService->cancelByPassenger($booking, null, null, $departureAt->modify('-12 hours'));

        self::assertSame(BookingStatus::CANCELLED_PASSENGER, $booking->getStatus());
        self::assertSame(60, $booking->getRefundAmount());
    }

    public function testDriverCancellationCascadesToAllBookingsWith100PercentRefund(): void
    {
        $driver = $this->createUser($this->em, 'driver@test.ma', 'Karim');
        $p1 = $this->createUser($this->em, 'p1@test.ma', 'Amina');
        $p2 = $this->createUser($this->em, 'p2@test.ma', 'Youssef');
        $vehicle = $this->createVehicle($this->em, $driver);
        $agadir = $this->createCity($this->em, 'Agadir');
        $marrakech = $this->createCity($this->em, 'Marrakech');

        $departureAt = new \DateTimeImmutable('+2 days');
        ['trip' => $trip, 'stops' => $stops] = $this->createMultiStopTrip(
            $this->em,
            $driver,
            $vehicle,
            [$agadir, $marrakech],
            $departureAt,
            seats: 3,
            segmentPrices: [90]
        );

        $b1 = $this->bookingManager->book($p1, $trip, $stops[0], $stops[1], 1);
        $b2 = $this->bookingManager->book($p2, $trip, $stops[0], $stops[1], 2);

        // Conducteur annule 3h avant le départ -> tous les passagers ont 100 %
        $result = $this->cancellationService->cancelByDriver($trip, null, null, $departureAt->modify('-3 hours'));

        self::assertSame(2, $result['affectedBookingsCount']);
        self::assertSame(TripStatus::CANCELLED, $trip->getStatus());
        self::assertSame(BookingStatus::CANCELLED_DRIVER, $b1->getStatus());
        self::assertSame(90, $b1->getRefundAmount());
        self::assertSame(BookingStatus::CANCELLED_DRIVER, $b2->getStatus());
        self::assertSame(180, $b2->getRefundAmount());
    }

    public function testCannotCancelAfterDeparture(): void
    {
        $driver = $this->createUser($this->em, 'driver@test.ma', 'Karim');
        $passenger = $this->createUser($this->em, 'p@test.ma', 'Youssef');
        $vehicle = $this->createVehicle($this->em, $driver);
        $agadir = $this->createCity($this->em, 'Agadir');
        $marrakech = $this->createCity($this->em, 'Marrakech');

        $departureAt = new \DateTimeImmutable('+1 day');
        ['trip' => $trip, 'stops' => $stops] = $this->createMultiStopTrip(
            $this->em,
            $driver,
            $vehicle,
            [$agadir, $marrakech],
            $departureAt,
            seats: 2,
            segmentPrices: [100]
        );

        $booking = $this->bookingManager->book($passenger, $trip, $stops[0], $stops[1], 1);

        $this->expectException(CancellationNotAllowedException::class);
        $this->cancellationService->cancelByPassenger($booking, null, null, $departureAt->modify('+10 minutes'));
    }
}
