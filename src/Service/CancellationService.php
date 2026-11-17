<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Enum\BookingStatus;
use App\Domain\Enum\TripStatus;
use App\Entity\Booking;
use App\Entity\Trip;
use App\Entity\User;
use App\Exception\CancellationNotAllowedException;
use App\Repository\BookingRepository;
use App\Repository\TripSegmentRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Service orchestrating distinct passenger and driver cancellation policies (T-203).
 *
 * Policies:
 * 1. Passenger cancellation:
 *    - Horizon > 24 hours: 100% refund.
 *    - Horizon <= 24 hours: 50% refund.
 *    - Horizon <= 0 (departure passed): Not allowed.
 *    - Atomic seat release on all consecutive segments with PESSIMISTIC_WRITE locks.
 *
 * 2. Driver cancellation:
 *    - Cascading cancellation of all confirmed bookings to CANCELLED_DRIVER with 100% refund.
 *    - Trip transitions to CANCELLED.
 */
final class CancellationService
{
    public const REFUND_THRESHOLD_HOURS = 24;
    public const FULL_REFUND_RATIO = 1.0;
    public const LATE_REFUND_RATIO = 0.5;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TripSegmentRepository $segmentRepository,
        private readonly BookingRepository $bookingRepository,
    ) {
    }

    /**
     * Preview refund calculation without modifying state (ideal for UI modal confirmation).
     *
     * @return array{ratio: float, refundAmount: int, hoursRemaining: float, policyNotice: string}
     * @throws CancellationNotAllowedException
     */
    public function previewPassengerRefund(Booking $booking, ?\DateTimeImmutable $now = null): array
    {
        $now ??= new \DateTimeImmutable();

        if ($booking->getStatus() !== BookingStatus::CONFIRMED) {
            throw new CancellationNotAllowedException('Cette réservation n\'est pas active ou est déjà annulée.');
        }

        $boardingTime = $booking->getBoardingStop()?->getEstimatedArrivalAt()
            ?? $booking->getTrip()?->getDepartureAt();

        if ($boardingTime === null) {
            throw new CancellationNotAllowedException('Impossible de déterminer l\'horaire de montée pour cette réservation.');
        }

        if ($boardingTime <= $now) {
            throw new CancellationNotAllowedException('Le départ pour ce tronçon est déjà passé. L\'annulation n\'est plus possible.');
        }

        $secondsRemaining = $boardingTime->getTimestamp() - $now->getTimestamp();
        $hoursRemaining = round($secondsRemaining / 3600, 2);

        if ($hoursRemaining > self::REFUND_THRESHOLD_HOURS) {
            $ratio = self::FULL_REFUND_RATIO;
            $policyNotice = sprintf('Annulation plus de %dh avant le départ : remboursement intégral (100%%).', self::REFUND_THRESHOLD_HOURS);
        } else {
            $ratio = self::LATE_REFUND_RATIO;
            $policyNotice = sprintf('Annulation à moins de %dh du départ : retenue de 50%% (remboursement 50%%).', self::REFUND_THRESHOLD_HOURS);
        }

        $refundAmount = (int) round(($booking->getTotalPrice() ?? 0) * $ratio);

        return [
            'ratio' => $ratio,
            'refundAmount' => $refundAmount,
            'hoursRemaining' => $hoursRemaining,
            'policyNotice' => $policyNotice,
        ];
    }

    /**
     * Cancels a booking by the passenger.
     * Re-increments available seats on all booked atomic segments within an ACID transaction.
     *
     * @throws CancellationNotAllowedException
     */
    public function cancelByPassenger(
        Booking $booking,
        ?User $requestUser = null,
        ?string $reason = null,
        ?\DateTimeImmutable $now = null
    ): Booking {
        $now ??= new \DateTimeImmutable();

        // 1. Ownership security guard
        if ($requestUser !== null
            && $booking->getPassenger()?->getId() !== $requestUser->getId()
            && !in_array('ROLE_ADMIN', $requestUser->getRoles(), true)
        ) {
            throw new CancellationNotAllowedException('Vous n\'êtes pas autorisé à annuler cette réservation.');
        }

        // 2. Pre-transaction validation & refund preview calculation
        $refundData = $this->previewPassengerRefund($booking, $now);
        $trip = $booking->getTrip();

        if ($trip === null || $trip->getStatus() !== TripStatus::SCHEDULED) {
            throw new CancellationNotAllowedException('Le trajet associé n\'est plus dans un état permettant l\'annulation.');
        }

        $boardingStop = $booking->getBoardingStop();
        $alightingStop = $booking->getAlightingStop();

        if ($boardingStop === null || $alightingStop === null) {
            throw new CancellationNotAllowedException('Les arrêts de la réservation sont invalides.');
        }

        // 3. ACID Transaction with Pessimistic Locking
        return $this->entityManager->wrapInTransaction(function (EntityManagerInterface $em) use (
            $booking,
            $trip,
            $boardingStop,
            $alightingStop,
            $refundData,
            $now
        ): Booking {
            // A. Lock and refresh the Trip
            $em->lock($trip, LockMode::PESSIMISTIC_WRITE);
            $em->refresh($trip);

            if ($trip->getStatus() !== TripStatus::SCHEDULED) {
                throw new CancellationNotAllowedException('Le trajet a été modifié ou annulé entre-temps.');
            }

            // B. Lock and refresh consecutive segments in monotonic segmentOrder ASC
            $segments = $this->segmentRepository->findBetweenStopOrders(
                $trip,
                $boardingStop->getStopOrder(),
                $alightingStop->getStopOrder(),
                lockForUpdate: true
            );

            $seatsToRestore = $booking->getSeatsBooked() ?? 0;
            $maxVehicleSeats = $trip->getVehicle()?->getAvailableSeats() ?? 99;

            // C. Atomically restore seats on each segment
            foreach ($segments as $segment) {
                $newAvailable = $segment->getAvailableSeats() + $seatsToRestore;
                if ($newAvailable > $maxVehicleSeats) {
                    $newAvailable = $maxVehicleSeats;
                }
                $segment->setAvailableSeats($newAvailable);
            }

            // D. Update booking status and refund
            $booking->setStatus(BookingStatus::CANCELLED_PASSENGER);
            $booking->setRefundAmount($refundData['refundAmount']);
            $booking->setCancelledAt($now);

            $em->flush();

            return $booking;
        });
    }

    /**
     * Cancels a trip by the driver.
     * Cascades 100% refund cancellations to all confirmed passengers.
     *
     * @return array{trip: Trip, affectedBookingsCount: int, totalRefundedAmount: int}
     * @throws CancellationNotAllowedException
     */
    public function cancelByDriver(
        Trip $trip,
        ?User $requestUser = null,
        ?string $reason = null,
        ?\DateTimeImmutable $now = null
    ): array {
        $now ??= new \DateTimeImmutable();

        // 1. Driver ownership guard
        if ($requestUser !== null
            && $trip->getDriver()?->getId() !== $requestUser->getId()
            && !in_array('ROLE_ADMIN', $requestUser->getRoles(), true)
        ) {
            throw new CancellationNotAllowedException('Vous n\'êtes pas autorisé à annuler ce trajet.');
        }

        if ($trip->getStatus() !== TripStatus::SCHEDULED) {
            throw new CancellationNotAllowedException('Seul un trajet planifié peut être annulé.');
        }

        if ($trip->getDepartureAt() <= $now) {
            throw new CancellationNotAllowedException('Le trajet est déjà parti ou dépassé, impossible de l\'annuler.');
        }

        // 2. ACID Transaction
        return $this->entityManager->wrapInTransaction(function (EntityManagerInterface $em) use (
            $trip,
            $now
        ): array {
            // A. Lock trip for update
            $em->lock($trip, LockMode::PESSIMISTIC_WRITE);
            $em->refresh($trip);

            if ($trip->getStatus() !== TripStatus::SCHEDULED) {
                throw new CancellationNotAllowedException('Le statut du trajet a changé entre-temps.');
            }

            $trip->setStatus(TripStatus::CANCELLED);

            // B. Find all confirmed bookings on this trip
            $confirmedBookings = $this->bookingRepository->findConfirmedByTrip($trip);
            $totalRefunded = 0;

            foreach ($confirmedBookings as $booking) {
                $refund = $booking->getTotalPrice() ?? 0;
                $booking->setStatus(BookingStatus::CANCELLED_DRIVER);
                $booking->setRefundAmount($refund);
                $booking->setCancelledAt($now);

                $totalRefunded += $refund;
            }

            $em->flush();

            return [
                'trip' => $trip,
                'affectedBookingsCount' => count($confirmedBookings),
                'totalRefundedAmount' => $totalRefunded,
            ];
        });
    }
}
