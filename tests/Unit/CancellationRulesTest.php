<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Enum\BookingStatus;
use App\Domain\Enum\TripStatus;
use App\Entity\Booking;
use App\Entity\Trip;
use App\Entity\TripSegment;
use App\Entity\TripStop;
use App\Entity\User;
use App\Exception\CancellationNotAllowedException;
use App\Repository\BookingRepository;
use App\Repository\TripSegmentRepository;
use App\Service\CancellationService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests validating asymmetric passenger & driver cancellation policies (T-203).
 */
final class CancellationRulesTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private TripSegmentRepository $segmentRepository;
    private BookingRepository $bookingRepository;
    private CancellationService $cancellationService;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->segmentRepository = $this->createStub(TripSegmentRepository::class);
        $this->bookingRepository = $this->createStub(BookingRepository::class);

        $this->cancellationService = new CancellationService(
            $this->entityManager,
            $this->segmentRepository,
            $this->bookingRepository
        );
    }

    public function testPassengerCancellationMoreThan24HoursBeforeDepartureGives100PercentRefund(): void
    {
        $now = new \DateTimeImmutable('2026-06-01 10:00:00');
        $departureTime = new \DateTimeImmutable('2026-06-03 10:00:00'); // 48h later (> 24h)

        $trip = (new Trip())->setStatus(TripStatus::SCHEDULED)->setDepartureAt($departureTime);
        $stop = (new TripStop())->setTrip($trip)->setEstimatedArrivalAt($departureTime);

        $booking = (new Booking())
            ->setStatus(BookingStatus::CONFIRMED)
            ->setTrip($trip)
            ->setBoardingStop($stop)
            ->setTotalPrice(200);

        $preview = $this->cancellationService->previewPassengerRefund($booking, $now);

        $this->assertSame(1.0, $preview['ratio']);
        $this->assertSame(200, $preview['refundAmount']);
    }

    public function testPassengerCancellationLessThan24HoursBeforeDepartureGives50PercentRefund(): void
    {
        $now = new \DateTimeImmutable('2026-06-01 10:00:00');
        $departureTime = new \DateTimeImmutable('2026-06-01 18:00:00'); // 8h later (< 24h)

        $trip = (new Trip())->setStatus(TripStatus::SCHEDULED)->setDepartureAt($departureTime);
        $stop = (new TripStop())->setTrip($trip)->setEstimatedArrivalAt($departureTime);

        $booking = (new Booking())
            ->setStatus(BookingStatus::CONFIRMED)
            ->setTrip($trip)
            ->setBoardingStop($stop)
            ->setTotalPrice(150);

        $preview = $this->cancellationService->previewPassengerRefund($booking, $now);

        $this->assertSame(0.5, $preview['ratio']);
        $this->assertSame(75, $preview['refundAmount']);
    }

    public function testPassengerCancellationAfterDepartureThrowsException(): void
    {
        $now = new \DateTimeImmutable('2026-06-01 12:00:00');
        $departureTime = new \DateTimeImmutable('2026-06-01 10:00:00'); // 2h in past

        $trip = (new Trip())->setStatus(TripStatus::SCHEDULED)->setDepartureAt($departureTime);
        $stop = (new TripStop())->setTrip($trip)->setEstimatedArrivalAt($departureTime);

        $booking = (new Booking())
            ->setStatus(BookingStatus::CONFIRMED)
            ->setTrip($trip)
            ->setBoardingStop($stop)
            ->setTotalPrice(100);

        $this->expectException(CancellationNotAllowedException::class);
        $this->expectExceptionMessage('déjà passé');

        $this->cancellationService->previewPassengerRefund($booking, $now);
    }

    public function testCancelByPassengerRestoresSegmentSeatsAtomically(): void
    {
        $now = new \DateTimeImmutable('2026-06-01 10:00:00');
        $departureTime = new \DateTimeImmutable('2026-06-03 10:00:00');

        $passenger = new User();
        $trip = (new Trip())->setStatus(TripStatus::SCHEDULED)->setDepartureAt($departureTime);

        $stop0 = (new TripStop())->setTrip($trip)->setStopOrder(0)->setEstimatedArrivalAt($departureTime);
        $stop1 = (new TripStop())->setTrip($trip)->setStopOrder(1)->setEstimatedArrivalAt($departureTime);

        $segment = (new TripSegment())
            ->setTrip($trip)
            ->setSegmentOrder(0)
            ->setAvailableSeats(1);

        $booking = (new Booking())
            ->setPassenger($passenger)
            ->setStatus(BookingStatus::CONFIRMED)
            ->setTrip($trip)
            ->setBoardingStop($stop0)
            ->setAlightingStop($stop1)
            ->setSeatsBooked(2)
            ->setTotalPrice(160);

        $this->segmentRepository
            ->method('findBetweenStopOrders')
            ->willReturn([$segment]);

        $mockEm = $this->createStub(EntityManagerInterface::class);
        $this->entityManager
            ->method('wrapInTransaction')
            ->willReturnCallback(static fn (callable $callback) => $callback($mockEm));

        $cancelledBooking = $this->cancellationService->cancelByPassenger($booking, $passenger, null, $now);

        // Status is CANCELLED_PASSENGER and 100% refunded (160 MAD)
        $this->assertSame(BookingStatus::CANCELLED_PASSENGER, $cancelledBooking->getStatus());
        $this->assertSame(160, $cancelledBooking->getRefundAmount());
        // Available seats restored: 1 + 2 = 3 seats
        $this->assertSame(3, $segment->getAvailableSeats());
    }

    public function testCancelByDriverCancelsAllConfirmedBookingsWith100PercentRefund(): void
    {
        $now = new \DateTimeImmutable('2026-06-01 10:00:00');
        $departureTime = new \DateTimeImmutable('2026-06-02 10:00:00');

        $driver = new User();
        $trip = (new Trip())
            ->setDriver($driver)
            ->setStatus(TripStatus::SCHEDULED)
            ->setDepartureAt($departureTime);

        $passenger1 = new User();
        $booking1 = (new Booking())
            ->setPassenger($passenger1)
            ->setStatus(BookingStatus::CONFIRMED)
            ->setTotalPrice(120);

        $passenger2 = new User();
        $booking2 = (new Booking())
            ->setPassenger($passenger2)
            ->setStatus(BookingStatus::CONFIRMED)
            ->setTotalPrice(80);

        $this->bookingRepository
            ->method('findConfirmedByTrip')
            ->willReturn([$booking1, $booking2]);

        $mockEm = $this->createStub(EntityManagerInterface::class);
        $this->entityManager
            ->method('wrapInTransaction')
            ->willReturnCallback(static fn (callable $callback) => $callback($mockEm));

        $result = $this->cancellationService->cancelByDriver($trip, $driver, 'Panne véhicule', $now);

        $this->assertSame(TripStatus::CANCELLED, $trip->getStatus());
        $this->assertSame(2, $result['affectedBookingsCount']);
        $this->assertSame(200, $result['totalRefundedAmount']);

        $this->assertSame(BookingStatus::CANCELLED_DRIVER, $booking1->getStatus());
        $this->assertSame(120, $booking1->getRefundAmount());
        $this->assertSame(BookingStatus::CANCELLED_DRIVER, $booking2->getStatus());
        $this->assertSame(80, $booking2->getRefundAmount());
    }
}
