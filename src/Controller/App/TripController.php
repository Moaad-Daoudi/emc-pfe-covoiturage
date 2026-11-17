<?php

declare(strict_types=1);

namespace App\Controller\App;

use App\Entity\Trip;
use App\Entity\TripStop;
use App\Repository\ReviewRepository;
use App\Repository\TripRepository;
use App\Service\OsrmService;
use App\Service\RideBookingManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

class TripController extends AbstractController
{
    #[Route('/trips/{id}', name: 'app_trip_show', methods: ['GET'])]
    public function show(
        int $id,
        Request $request,
        TripRepository $tripRepository,
        RideBookingManager $bookingManager,
        ReviewRepository $reviewRepository,
        OsrmService $osrmService
    ): Response {
        $trip = $tripRepository->find($id);

        if ($trip === null) {
            throw new NotFoundHttpException(sprintf('Trajet #%d introuvable.', $id));
        }

        // Ordered stops
        $stops = $trip->getStops()->toArray();
        usort($stops, static fn(TripStop $a, TripStop $b) => $a->getStopOrder() <=> $b->getStopOrder());

        // Ordered segments
        $segments = $trip->getSegments()->toArray();
        usort($segments, static fn($a, $b) => $a->getSegmentOrder() <=> $b->getSegmentOrder());

        // Driver stats
        $driver = $trip->getDriver();
        $driverStats = ['average' => 4.9, 'count' => 12];
        if ($driver !== null) {
            $stats = $reviewRepository->getRatingStatsForUser($driver);
            if ($stats['count'] > 0) {
                $driverStats = $stats;
            }
        }
        $isSuperDriver = ($driverStats['average'] !== null && $driverStats['average'] >= 4.7);

        // Pre-selection from query params (from / to stop IDs)
        $fromStopId = $request->query->getInt('from', 0);
        $toStopId = $request->query->getInt('to', 0);

        $selectedFromStop = $stops[0] ?? null;
        $selectedToStop = !empty($stops) ? $stops[array_key_last($stops)] : null;

        if ($fromStopId > 0 && $toStopId > 0) {
            $candidateFrom = null;
            $candidateTo = null;
            foreach ($stops as $s) {
                if ($s->getId() === $fromStopId) {
                    $candidateFrom = $s;
                }
                if ($s->getId() === $toStopId) {
                    $candidateTo = $s;
                }
            }
            if ($candidateFrom !== null && $candidateTo !== null && $candidateFrom->getStopOrder() < $candidateTo->getStopOrder()) {
                $selectedFromStop = $candidateFrom;
                $selectedToStop = $candidateTo;
            }
        }

        // Compute sub-trip combinations matrix for instantaneous client-side reactivity
        $matrix = [];
        $stopCount = count($stops);

        for ($i = 0; $i < $stopCount; $i++) {
            for ($j = $i + 1; $j < $stopCount; $j++) {
                $fStop = $stops[$i];
                $tStop = $stops[$j];

                try {
                    $availableSeats = $bookingManager->getAvailableSeats($trip, $fStop, $tStop);
                    $price = $bookingManager->calculateSubTripPrice($trip, $fStop, $tStop, 1);
                    $subSegments = $bookingManager->resolveSegmentsBetweenStops($trip, $fStop, $tStop);
                    $distKm = array_sum(array_map(static fn($seg) => (float) ($seg->getDistanceKm() ?? 0.0), $subSegments));
                    
                    // Identify bottleneck segment if any
                    $bottleneck = null;
                    $minSeats = $availableSeats;
                    foreach ($subSegments as $seg) {
                        if ($seg->getAvailableSeats() === $minSeats) {
                            $fromCity = $seg->getFromStop()?->getCity()?->getName() ?? '';
                            $toCity = $seg->getToStop()?->getCity()?->getName() ?? '';
                            $bottleneck = sprintf('%s → %s', $fromCity, $toCity);
                            break;
                        }
                    }
                } catch (\Throwable) {
                    $availableSeats = 0;
                    $price = $trip->getPriceTotal() ?? 55;
                    $distKm = $trip->getDistanceKm() ?? 100.0;
                    $bottleneck = null;
                }

                $key = $fStop->getId() . '_' . $tStop->getId();
                $matrix[$key] = [
                    'fromId' => $fStop->getId(),
                    'toId' => $tStop->getId(),
                    'fromCity' => $fStop->getCity()?->getName() ?? '',
                    'toCity' => $tStop->getCity()?->getName() ?? '',
                    'availableSeats' => $availableSeats,
                    'price' => $price,
                    'distanceKm' => round($distKm, 0),
                    'bottleneck' => $bottleneck,
                ];
            }
        }

        // Active selection initial data
        $activeKey = ($selectedFromStop && $selectedToStop) ? ($selectedFromStop->getId() . '_' . $selectedToStop->getId()) : null;
        $activeSubTrip = ($activeKey && isset($matrix[$activeKey])) ? $matrix[$activeKey] : [
            'price' => $trip->getPriceTotal() ?? 110,
            'availableSeats' => $trip->getTotalSeats() ?? 3,
            'distanceKm' => $trip->getDistanceKm() ?? 230,
            'bottleneck' => null,
        ];

        // Format waypoints for Leaflet map
        $mapWaypoints = [];
        foreach ($stops as $s) {
            $city = $s->getCity();
            $mapWaypoints[] = [
                'id' => $s->getId(),
                'order' => $s->getStopOrder(),
                'city' => $city?->getName() ?? 'Ville',
                'meetingPoint' => $s->getMeetingPoint() ?? 'Point de rendez-vous',
                'time' => $s->getEstimatedArrivalAt()?->format('H:i') ?? $trip->getDepartureAt()?->format('H:i'),
                'lat' => $city?->getLatitude() ?? 30.4278,
                'lng' => $city?->getLongitude() ?? -9.5981,
                'isInitial' => ($s->getStopOrder() === 0),
                'isFinal' => ($s->getStopOrder() === ($stopCount - 1)),
            ];
        }

        // Calculate real OSRM road geometry and duration
        try {
            $routeResult = $osrmService->calculateRouteForTrip($trip);
        } catch (\Throwable) {
            $routeResult = null;
        }

        return $this->render('app/trip/show.html.twig', [
            'trip' => $trip,
            'stops' => $stops,
            'segments' => $segments,
            'driver' => $driver,
            'driverStats' => $driverStats,
            'isSuperDriver' => $isSuperDriver,
            'selectedFromStop' => $selectedFromStop,
            'selectedToStop' => $selectedToStop,
            'activeSubTrip' => $activeSubTrip,
            'routeResult' => $routeResult,
            'routeGeometryJson' => json_encode($routeResult?->geometry ?? ['type' => 'LineString', 'coordinates' => []], JSON_THROW_ON_ERROR),
            'matrixJson' => json_encode($matrix, JSON_THROW_ON_ERROR),
            'mapWaypointsJson' => json_encode($mapWaypoints, JSON_THROW_ON_ERROR),
        ]);
    }
}