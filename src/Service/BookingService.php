<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Booking;
use App\Entity\Trip;
use App\Entity\TripStop;
use App\Entity\User;

/**
 * Service facade delegating to RideBookingManager.
 * Ensures backward compatibility and seamless autowiring.
 */
class BookingService
{
    public function __construct(
        private readonly RideBookingManager $rideBookingManager
    ) {
    }

    public function getAvailableSeats(Trip $trip, TripStop $boardingStop, TripStop $alightingStop): int
    {
        return $this->rideBookingManager->getAvailableSeats($trip, $boardingStop, $alightingStop);
    }

    public function getAvailableSeatsForPath(Trip $trip, TripStop $boardingStop, TripStop $alightingStop): int
    {
        return $this->rideBookingManager->getAvailableSeats($trip, $boardingStop, $alightingStop);
    }

    public function calculateSubTripPrice(Trip $trip, TripStop $boardingStop, TripStop $alightingStop, int $seats = 1): int
    {
        return $this->rideBookingManager->calculateSubTripPrice($trip, $boardingStop, $alightingStop, $seats);
    }

    public function calculatePriceForPath(Trip $trip, TripStop $boardingStop, TripStop $alightingStop, int $seatsCount = 1): int
    {
        return $this->rideBookingManager->calculateSubTripPrice($trip, $boardingStop, $alightingStop, $seatsCount);
    }

    public function book(User $passenger, Trip $trip, TripStop $boardingStop, TripStop $alightingStop, int $seats = 1): Booking
    {
        return $this->rideBookingManager->book($passenger, $trip, $boardingStop, $alightingStop, $seats);
    }

    public function bookTrip(User $passenger, Trip $trip, TripStop $boardingStop, TripStop $alightingStop, int $seatsCount = 1): Booking
    {
        return $this->rideBookingManager->book($passenger, $trip, $boardingStop, $alightingStop, $seatsCount);
    }
}
