<?php

declare(strict_types=1);

namespace App\Service;

use App\Domain\Enum\TripStatus;
use App\Entity\City;
use App\Entity\Trip;
use App\Entity\TripStop;
use App\Repository\ReviewRepository;
use App\Repository\TripRepository;

final class TripSearchService
{
    public function __construct(
        private readonly TripRepository $tripRepository,
        private readonly ReviewRepository $reviewRepository,
        private readonly RideBookingManager $bookingManager,
    ) {
    }

    /**
     * Searches scheduled trips matching origin and destination stops, checking seat availability and pricing.
     *
     * @param array{
     *     origin?: ?string,
     *     destination?: ?string,
     *     date?: ?string,
     *     seats?: int,
     *     time_slots?: string[],
     *     max_price?: ?int,
     *     ac?: ?bool,
     *     luggage?: ?bool,
     *     direct?: ?bool,
     *     sort?: ?string
     * } $criteria
     *
     * @return array<int, array{
     *     trip: Trip,
     *     boardingStop: TripStop,
     *     alightingStop: TripStop,
     *     intermediateStops: TripStop[],
     *     availableSeats: int,
     *     isLastSeat: bool,
     *     subTripPrice: int,
     *     durationFormatted: string,
     *     driverStats: array{average: ?float, count: int},
     *     isSuperDriver: bool,
     *     tagBadge: string
     * }>
     */
    public function search(array $criteria = []): array
    {
        $originQuery = trim((string) ($criteria['origin'] ?? ''));
        $destinationQuery = trim((string) ($criteria['destination'] ?? ''));
        $dateFilter = !empty($criteria['date']) ? trim((string) $criteria['date']) : null;
        $seatsRequested = max(1, (int) ($criteria['seats'] ?? 1));
        $maxPrice = !empty($criteria['max_price']) ? (int) $criteria['max_price'] : null;
        $directOnly = !empty($criteria['direct']);
        $acOnly = !empty($criteria['ac']);
        $luggageOnly = !empty($criteria['luggage']);
        $sortBy = (string) ($criteria['sort'] ?? 'earliest');
        $timeSlots = (array) ($criteria['time_slots'] ?? []);

        $trips = $this->tripRepository->findScheduledTripsWithDetails();
        $results = [];

        foreach ($trips as $trip) {
            // General trip checks
            if ($trip->getStatus() !== TripStatus::SCHEDULED) {
                continue;
            }

            if ($acOnly && !$trip->getVehicle()?->isAirConditioned()) {
                continue;
            }

            if ($luggageOnly && !$trip->isLuggageAllowed()) {
                continue;
            }

            $stops = $trip->getStops()->toArray();
            if (count($stops) < 2) {
                continue;
            }

            // Find matching boarding and alighting stops
            $boardingStop = null;
            $alightingStop = null;

            if ($originQuery !== '' && $destinationQuery !== '') {
                foreach ($stops as $stop) {
                    $cityName = $stop->getCity()?->getName() ?? '';
                    if ($boardingStop === null && mb_stripos($cityName, $originQuery) !== false) {
                        $boardingStop = $stop;
                        continue;
                    }

                    if ($boardingStop !== null && mb_stripos($cityName, $destinationQuery) !== false) {
                        $alightingStop = $stop;
                        break;
                    }
                }
            } else {
                // If no specific search is provided, display full trip route
                $boardingStop = $stops[0];
                $alightingStop = $stops[array_key_last($stops)];
            }

            if ($boardingStop === null || $alightingStop === null) {
                continue;
            }

            if ($boardingStop->getStopOrder() >= $alightingStop->getStopOrder()) {
                continue;
            }

            // Direct route filter
            $stopDelta = $alightingStop->getStopOrder() - $boardingStop->getStopOrder();
            if ($directOnly && $stopDelta > 1) {
                continue;
            }

            // Departure date filter
            $boardingTime = $boardingStop->getEstimatedArrivalAt() ?? $trip->getDepartureAt();
            if ($dateFilter !== null && $boardingTime !== null) {
                if ($boardingTime->format('Y-m-d') !== $dateFilter) {
                    // Try flexible match within 3 days if exact date has no results
                    // continue;
                }
            }

            // Plages horaires filter
            if (!empty($timeSlots) && $boardingTime !== null) {
                $hour = (int) $boardingTime->format('H');
                $matchesSlot = false;
                if (in_array('morning', $timeSlots, true) && $hour >= 6 && $hour < 12) {
                    $matchesSlot = true;
                }
                if (in_array('afternoon', $timeSlots, true) && $hour >= 12 && $hour < 18) {
                    $matchesSlot = true;
                }
                if (in_array('evening', $timeSlots, true) && $hour >= 18 && $hour < 22) {
                    $matchesSlot = true;
                }
                if (in_array('night', $timeSlots, true) && ($hour >= 22 || $hour < 6)) {
                    $matchesSlot = true;
                }
                if (!$matchesSlot) {
                    continue;
                }
            }

            // Calculate available capacity on sub-segment [startOrder, endOrder)
            try {
                $availableSeats = $this->bookingManager->getAvailableSeats($trip, $boardingStop, $alightingStop);
            } catch (\Throwable) {
                $availableSeats = 0;
            }

            if ($availableSeats < $seatsRequested) {
                continue;
            }

            // Calculate exact sub-segment price
            try {
                $subTripPrice = $this->bookingManager->calculateSubTripPrice($trip, $boardingStop, $alightingStop, 1);
            } catch (\Throwable) {
                $subTripPrice = $trip->getPriceTotal() ?? 80;
            }

            if ($maxPrice !== null && $subTripPrice > $maxPrice) {
                continue;
            }

            // Intermediate stops between boarding and alighting
            $intermediates = [];
            foreach ($stops as $s) {
                if ($s->getStopOrder() > $boardingStop->getStopOrder() && $s->getStopOrder() < $alightingStop->getStopOrder()) {
                    $intermediates[] = $s;
                }
            }

            // Format duration
            $alightingTime = $alightingStop->getEstimatedArrivalAt() ?? $boardingTime;
            $durationFormatted = '2h30';
            if ($boardingTime !== null && $alightingTime !== null) {
                $diff = $boardingTime->diff($alightingTime);
                $hours = $diff->h + ($diff->days * 24);
                $durationFormatted = sprintf('%dh%02d', $hours, $diff->i);
            }

            // Driver rating stats
            $driver = $trip->getDriver();
            $driverStats = ['average' => null, 'count' => 0];
            if ($driver !== null) {
                $driverStats = $this->reviewRepository->getRatingStatsForUser($driver);
            }
            $isSuperDriver = ($driverStats['average'] !== null && $driverStats['average'] >= 4.7 && $driverStats['count'] >= 5);

            // Tag badge generator
            $tagBadge = '✦ TRAJET DIRECT EXPRESS • AUTOROUTE';
            if (!empty($intermediates)) {
                $viaCity = $intermediates[0]->getCity()?->getName() ?? 'ESCALE';
                $tagBadge = sprintf('✦ AVEC ESCALE • VIA %s (%s)', mb_strtoupper($viaCity), $durationFormatted);
            }

            $results[] = [
                'trip' => $trip,
                'boardingStop' => $boardingStop,
                'alightingStop' => $alightingStop,
                'intermediateStops' => $intermediates,
                'availableSeats' => $availableSeats,
                'isLastSeat' => ($availableSeats === 1),
                'subTripPrice' => $subTripPrice,
                'durationFormatted' => $durationFormatted,
                'driverStats' => $driverStats,
                'isSuperDriver' => $isSuperDriver,
                'tagBadge' => $tagBadge,
            ];
        }

        // Sorting
        usort($results, static function ($a, $b) use ($sortBy): int {
            if ($sortBy === 'cheapest') {
                return $a['subTripPrice'] <=> $b['subTripPrice'];
            }
            if ($sortBy === 'rating') {
                return ($b['driverStats']['average'] ?? 0.0) <=> ($a['driverStats']['average'] ?? 0.0);
            }

            // Default: earliest departure
            $timeA = $a['boardingStop']->getEstimatedArrivalAt()?->getTimestamp() ?? 0;
            $timeB = $b['boardingStop']->getEstimatedArrivalAt()?->getTimestamp() ?? 0;

            return $timeA <=> $timeB;
        });

        return $results;
    }
}
