<?php

declare(strict_types=1);

namespace App\Tests\Integration;

use App\Entity\TripSegment;
use App\Exception\InvalidTripSegmentException;
use App\Exception\SeatsNotAvailableException;
use App\Exception\SelfBookingNotAllowedException;
use App\Repository\TripSegmentRepository;
use App\Service\RideBookingManager;
use App\Tests\Support\CarpoolingTestHelperTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class TripSegmentAvailabilityTest extends KernelTestCase
{
    use CarpoolingTestHelperTrait;

    private EntityManagerInterface $em;
    private RideBookingManager $bookingManager;

    protected function setUp(): void
    {
        self::bootKernel(['environment' => 'test']);
        /** @var EntityManagerInterface $em */
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->em = $em;

        /** @var TripSegmentRepository $segmentRepo */
        $segmentRepo = $this->em->getRepository(TripSegment::class);
        $this->bookingManager = new RideBookingManager($this->em, $segmentRepo);

        $this->resetDatabase($this->em);
    }

    public function testDisjointSubSegmentsShareSameSeatWithoutOverbooking(): void
    {
        $driver = $this->createUser($this->em, 'driver@test.ma', 'Karim');
        $p1 = $this->createUser($this->em, 'p1@test.ma', 'Youssef');
        $p2 = $this->createUser($this->em, 'p2@test.ma', 'Salma');
        $p3 = $this->createUser($this->em, 'p3@test.ma', 'Omar');
        $vehicle = $this->createVehicle($this->em, $driver);

        $agadir = $this->createCity($this->em, 'Agadir');
        $essaouira = $this->createCity($this->em, 'Essaouira');
        $marrakech = $this->createCity($this->em, 'Marrakech');

        // Trajet avec 1 seule place : Agadir(0) -> Essaouira(1) -> Marrakech(2)
        ['trip' => $trip, 'stops' => $stops] = $this->createMultiStopTrip(
            $this->em,
            $driver,
            $vehicle,
            [$agadir, $essaouira, $marrakech],
            new \DateTimeImmutable('+2 days'),
            seats: 1,
            segmentPrices: [65, 55]
        );

        // Passager 1 réserve Agadir -> Essaouira (segment 0 uniquement)
        $b1 = $this->bookingManager->book($p1, $trip, $stops[0], $stops[1], 1);
        self::assertSame(65, $b1->getTotalPrice());

        // La place reste disponible sur Essaouira -> Marrakech (segment 1)
        self::assertSame(1, $this->bookingManager->getAvailableSeats($trip, $stops[1], $stops[2]));
        // Mais 0 place disponible sur le trajet complet Agadir -> Marrakech
        self::assertSame(0, $this->bookingManager->getAvailableSeats($trip, $stops[0], $stops[2]));

        // Passager 2 peut réserver Essaouira -> Marrakech sur le même siège libéré à Essaouira
        $b2 = $this->bookingManager->book($p2, $trip, $stops[1], $stops[2], 1);
        self::assertSame(55, $b2->getTotalPrice());

        // Passager 3 tente Essaouira -> Marrakech -> refusé
        $this->expectException(SeatsNotAvailableException::class);
        $this->bookingManager->book($p3, $trip, $stops[1], $stops[2], 1);
    }

    public function testDriverCannotBookOwnTrip(): void
    {
        $driver = $this->createUser($this->em, 'driver@test.ma', 'Karim');
        $vehicle = $this->createVehicle($this->em, $driver);
        $agadir = $this->createCity($this->em, 'Agadir');
        $marrakech = $this->createCity($this->em, 'Marrakech');

        ['trip' => $trip, 'stops' => $stops] = $this->createMultiStopTrip(
            $this->em,
            $driver,
            $vehicle,
            [$agadir, $marrakech],
            new \DateTimeImmutable('+2 days'),
            seats: 2,
            segmentPrices: [110]
        );

        $this->expectException(SelfBookingNotAllowedException::class);
        $this->bookingManager->book($driver, $trip, $stops[0], $stops[1], 1);
    }

    public function testReverseStopOrderThrowsInvalidSegmentException(): void
    {
        $driver = $this->createUser($this->em, 'driver@test.ma', 'Karim');
        $passenger = $this->createUser($this->em, 'p@test.ma', 'Youssef');
        $vehicle = $this->createVehicle($this->em, $driver);
        $agadir = $this->createCity($this->em, 'Agadir');
        $marrakech = $this->createCity($this->em, 'Marrakech');

        ['trip' => $trip, 'stops' => $stops] = $this->createMultiStopTrip(
            $this->em,
            $driver,
            $vehicle,
            [$agadir, $marrakech],
            new \DateTimeImmutable('+2 days'),
            seats: 2,
            segmentPrices: [110]
        );

        $this->expectException(InvalidTripSegmentException::class);
        $this->bookingManager->book($passenger, $trip, $stops[1], $stops[0], 1);
    }
}
