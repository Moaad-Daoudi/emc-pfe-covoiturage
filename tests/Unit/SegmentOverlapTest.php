<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Domain\Enum\TripStatus;
use App\Entity\City;
use App\Entity\Trip;
use App\Entity\TripSegment;
use App\Entity\TripStop;
use App\Exception\InvalidTripSegmentException;
use App\Repository\TripSegmentRepository;
use App\Service\RideBookingManager;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

/**
 * Unit test validating multi-leg segment inventory calculations and [i, j) slice invariants.
 */
final class SegmentOverlapTest extends TestCase
{
    private EntityManagerInterface $entityManager;
    private TripSegmentRepository $segmentRepository;
    private RideBookingManager $bookingManager;

    protected function setUp(): void
    {
        $this->entityManager = $this->createStub(EntityManagerInterface::class);
        $this->segmentRepository = $this->createStub(TripSegmentRepository::class);
        $this->bookingManager = new RideBookingManager($this->entityManager, $this->segmentRepository);
    }

    public function testResolveSegmentsThrowsExceptionWhenStopsBelongToDifferentTrips(): void
    {
        $trip1 = (new Trip())->setStatus(TripStatus::SCHEDULED);
        $trip2 = (new Trip())->setStatus(TripStatus::SCHEDULED);

        $reflection = new \ReflectionProperty(Trip::class, 'id');
        $reflection->setValue($trip1, 1);
        $reflection->setValue($trip2, 2);

        $stop1 = (new TripStop())->setTrip($trip1)->setStopOrder(0);
        $stop2 = (new TripStop())->setTrip($trip2)->setStopOrder(1);

        $this->expectException(InvalidTripSegmentException::class);
        $this->expectExceptionMessage('Les arrêts sélectionnés n\'appartiennent pas à ce trajet.');

        $this->bookingManager->resolveSegments($trip1, $stop1, $stop2);
    }

    public function testResolveSegmentsThrowsExceptionWhenDropoffIsBeforeBoarding(): void
    {
        $trip = (new Trip())->setStatus(TripStatus::SCHEDULED);
        $reflection = new \ReflectionProperty(Trip::class, 'id');
        $reflection->setValue($trip, 1);

        $stopDeparture = (new TripStop())->setTrip($trip)->setStopOrder(2);
        $stopArrival = (new TripStop())->setTrip($trip)->setStopOrder(0);

        $this->expectException(InvalidTripSegmentException::class);
        $this->expectExceptionMessage('doit être postérieur');

        $this->bookingManager->resolveSegments($trip, $stopDeparture, $stopArrival);
    }

    public function testGetAvailableSeatsReturnsBottleneckCapacityAcrossSegments(): void
    {
        // Route: Agadir (0) -> Essaouira (1) -> Marrakech (2)
        $trip = (new Trip())->setStatus(TripStatus::SCHEDULED);
        $reflection = new \ReflectionProperty(Trip::class, 'id');
        $reflection->setValue($trip, 10);

        $futureDate = new \DateTimeImmutable('+2 days');
        $stop0 = (new TripStop())->setTrip($trip)->setStopOrder(0)->setEstimatedArrivalAt($futureDate);
        $stop1 = (new TripStop())->setTrip($trip)->setStopOrder(1)->setEstimatedArrivalAt($futureDate);
        $stop2 = (new TripStop())->setTrip($trip)->setStopOrder(2)->setEstimatedArrivalAt($futureDate);

        // Segment 0: Agadir -> Essaouira (3 seats available, 80 MAD)
        $segment0 = (new TripSegment())
            ->setTrip($trip)
            ->setSegmentOrder(0)
            ->setAvailableSeats(3)
            ->setPrice(80);

        // Segment 1: Essaouira -> Marrakech (1 seat available - BOTTLENECK, 90 MAD)
        $segment1 = (new TripSegment())
            ->setTrip($trip)
            ->setSegmentOrder(1)
            ->setAvailableSeats(1)
            ->setPrice(90);

        $this->segmentRepository
            ->method('findBetweenStopOrders')
            ->willReturn([$segment0, $segment1]);

        $availableSeats = $this->bookingManager->getAvailableSeats($trip, $stop0, $stop2);

        // Bottleneck is min(3, 1) = 1 seat
        $this->assertSame(1, $availableSeats);
    }

    public function testCalculateSubTripPriceSumsConsecutiveSegments(): void
    {
        $trip = (new Trip())->setStatus(TripStatus::SCHEDULED);
        $reflection = new \ReflectionProperty(Trip::class, 'id');
        $reflection->setValue($trip, 20);

        $stop0 = (new TripStop())->setTrip($trip)->setStopOrder(0);
        $stop2 = (new TripStop())->setTrip($trip)->setStopOrder(2);

        $segment0 = (new TripSegment())->setTrip($trip)->setSegmentOrder(0)->setPrice(70);
        $segment1 = (new TripSegment())->setTrip($trip)->setSegmentOrder(1)->setPrice(85);

        $this->segmentRepository
            ->method('findBetweenStopOrders')
            ->willReturn([$segment0, $segment1]);

        // 2 passengers: (70 + 85) * 2 = 310 MAD
        $totalPrice = $this->bookingManager->calculateSubTripPrice($trip, $stop0, $stop2, seats: 2);

        $this->assertSame(310, $totalPrice);
    }
}
