<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\RouteResult;
use App\Entity\City;
use App\Entity\Trip;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Service interfacing with OSRM (Open Source Routing Machine).
 * Provides driving route distance, duration and GeoJSON polyline geometry.
 * Features:
 *  - High-performance caching layer with CacheInterface
 *  - Strict coordinates formatting (longitude,latitude per OSRM specs)
 *  - Resilient offline fallback using Moroccan road matrix and Haversine routing
 */
class OsrmService
{
    private const DEFAULT_BASE_URL = 'https://router.project-osrm.org';
    private const DEFAULT_USER_AGENT = 'SafarCar-Morocco-PFE/1.0 (contact@safarcar.ma)';
    private const DEFAULT_CACHE_TTL = 2592000; // 30 days
    private const HIGHWAY_WINDING_FACTOR = 1.25; // Moroccan highway/national road terrain winding factor

    /**
     * Exact known motorway/expressway driving distances and durations between major Moroccan cities.
     * key: lower(city1)_lower(city2)
     *
     * @var array<string, array{distanceKm: float, durationMinutes: int}>
     */
    private const MOROCCAN_ROAD_MATRIX = [
        'agadir_marrakech' => ['distanceKm' => 230.0, 'durationMinutes' => 225],
        'marrakech_agadir' => ['distanceKm' => 230.0, 'durationMinutes' => 225],
        'marrakech_casablanca' => ['distanceKm' => 240.0, 'durationMinutes' => 165],
        'casablanca_marrakech' => ['distanceKm' => 240.0, 'durationMinutes' => 165],
        'agadir_essaouira' => ['distanceKm' => 175.0, 'durationMinutes' => 165],
        'essaouira_agadir' => ['distanceKm' => 175.0, 'durationMinutes' => 165],
        'essaouira_marrakech' => ['distanceKm' => 178.0, 'durationMinutes' => 150],
        'marrakech_essaouira' => ['distanceKm' => 178.0, 'durationMinutes' => 150],
        'agadir_chichaoua' => ['distanceKm' => 165.0, 'durationMinutes' => 135],
        'chichaoua_agadir' => ['distanceKm' => 165.0, 'durationMinutes' => 135],
        'chichaoua_marrakech' => ['distanceKm' => 85.0, 'durationMinutes' => 70],
        'marrakech_chichaoua' => ['distanceKm' => 85.0, 'durationMinutes' => 70],
        'casablanca_rabat' => ['distanceKm' => 87.0, 'durationMinutes' => 60],
        'rabat_casablanca' => ['distanceKm' => 87.0, 'durationMinutes' => 60],
        'rabat_tanger' => ['distanceKm' => 250.0, 'durationMinutes' => 165],
        'tanger_rabat' => ['distanceKm' => 250.0, 'durationMinutes' => 165],
        'rabat_kenitra' => ['distanceKm' => 45.0, 'durationMinutes' => 35],
        'kenitra_rabat' => ['distanceKm' => 45.0, 'durationMinutes' => 35],
        'casablanca_el jadida' => ['distanceKm' => 100.0, 'durationMinutes' => 75],
        'el jadida_casablanca' => ['distanceKm' => 100.0, 'durationMinutes' => 75],
        'marrakech_benguerir' => ['distanceKm' => 75.0, 'durationMinutes' => 50],
        'benguerir_marrakech' => ['distanceKm' => 75.0, 'durationMinutes' => 50],
        'benguerir_settat' => ['distanceKm' => 95.0, 'durationMinutes' => 60],
        'settat_benguerir' => ['distanceKm' => 95.0, 'durationMinutes' => 60],
        'settat_casablanca' => ['distanceKm' => 70.0, 'durationMinutes' => 55],
        'casablanca_settat' => ['distanceKm' => 70.0, 'durationMinutes' => 55],
        'agadir_taroudant' => ['distanceKm' => 80.0, 'durationMinutes' => 65],
        'taroudant_agadir' => ['distanceKm' => 80.0, 'durationMinutes' => 65],
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger = new NullLogger(),
        private readonly string $baseUrl = self::DEFAULT_BASE_URL,
        private readonly string $userAgent = self::DEFAULT_USER_AGENT,
        private readonly int $cacheTtl = self::DEFAULT_CACHE_TTL
    ) {
    }

    /**
     * Calculate driving route across an ordered list of waypoints.
     * Each waypoint must be an array:
     *   - associative: ['lat' => 30.42, 'lon' => -9.59] (or 'lng')
     *   - indexed: [lon, lat] or [lat, lon] (if lat > lon, standard for Morocco)
     *
     * @param array<int, array<string, float>|array<int, float>> $waypoints
     */
    public function calculateRoute(array $waypoints): RouteResult
    {
        $normalizedWaypoints = $this->normalizeWaypoints($waypoints);
        if (count($normalizedWaypoints) < 2) {
            throw new \InvalidArgumentException('At least two valid waypoints are required to calculate an OSRM route.');
        }

        // Build coordinates string: lon1,lat1;lon2,lat2;...
        $coordsParts = [];
        foreach ($normalizedWaypoints as $pt) {
            $coordsParts[] = sprintf('%.5f,%.5f', $pt['lon'], $pt['lat']);
        }
        $coordsString = implode(';', $coordsParts);
        $cacheKey = 'osrm_route_' . md5($coordsString);

        try {
            $cachedData = $this->cache->get($cacheKey, function (ItemInterface $item) use ($coordsString): ?array {
                $item->expiresAfter($this->cacheTtl);

                return $this->fetchFromOsrmApi($coordsString);
            });

            if ($cachedData !== null) {
                return $this->hydrateResult($cachedData);
            }
        } catch (Throwable $e) {
            $this->logger->warning('OSRM API request failed, falling back to internal Moroccan road engine', [
                'coords' => $coordsString,
                'error' => $e->getMessage(),
            ]);
        }

        return $this->calculateFallbackRoute($normalizedWaypoints);
    }

    /**
     * Calculate route for a Trip entity by parsing its departure, arrival and stops.
     */
    public function calculateRouteForTrip(Trip $trip): RouteResult
    {
        $waypoints = [];
        $stops = $trip->getStops()->toArray();

        if (count($stops) >= 2) {
            usort($stops, static fn ($a, $b) => $a->getStopOrder() <=> $b->getStopOrder());
            foreach ($stops as $stop) {
                $city = $stop->getCity();
                if ($city !== null) {
                    $waypoints[] = [
                        'lat' => $city->getLatitude(),
                        'lon' => $city->getLongitude(),
                        'cityName' => $city->getName(),
                    ];
                }
            }
        } else {
            // Fallback to departure & arrival cities
            $dep = $trip->getDepartureCity();
            $arr = $trip->getArrivalCity();
            if ($dep !== null && $arr !== null) {
                $waypoints = [
                    ['lat' => $dep->getLatitude(), 'lon' => $dep->getLongitude(), 'cityName' => $dep->getName()],
                    ['lat' => $arr->getLatitude(), 'lon' => $arr->getLongitude(), 'cityName' => $arr->getName()],
                ];
            }
        }

        return $this->calculateRoute($waypoints);
    }

    /**
     * Calculate route between two cities with optional intermediate cities.
     *
     * @param array<int, City> $intermediates
     */
    public function calculateRouteBetweenCities(City $origin, City $destination, array $intermediates = []): RouteResult
    {
        $waypoints = [
            ['lat' => $origin->getLatitude(), 'lon' => $origin->getLongitude(), 'cityName' => $origin->getName()],
        ];

        foreach ($intermediates as $intermediate) {
            $waypoints[] = [
                'lat' => $intermediate->getLatitude(),
                'lon' => $intermediate->getLongitude(),
                'cityName' => $intermediate->getName(),
            ];
        }

        $waypoints[] = [
            'lat' => $destination->getLatitude(),
            'lon' => $destination->getLongitude(),
            'cityName' => $destination->getName(),
        ];

        return $this->calculateRoute($waypoints);
    }

    /**
     * Helper to format minutes into human-readable duration (e.g. 215 -> '3h35').
     */
    public static function formatDuration(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        if ($h === 0) {
            return sprintf('%d min', $m);
        }

        return sprintf('%dh%02d', $h, $m);
    }

    /**
     * Call the official OSRM routing HTTP endpoint.
     *
     * @return array<string, mixed>|null
     */
    private function fetchFromOsrmApi(string $coordsString): ?array
    {
        $url = sprintf(
            '%s/route/v1/driving/%s?overview=full&geometries=geojson&steps=true',
            rtrim($this->baseUrl, '/'),
            $coordsString
        );

        $response = $this->httpClient->request('GET', $url, [
            'headers' => [
                'User-Agent' => $this->userAgent,
                'Accept' => 'application/json',
            ],
            'timeout' => 1.2,
        ]);

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $data = $response->toArray(false);
        if (($data['code'] ?? '') !== 'Ok' || empty($data['routes'])) {
            return null;
        }

        return $data;
    }

    /**
     * Hydrate a RouteResult from raw OSRM JSON response.
     *
     * @param array<string, mixed> $data
     */
    private function hydrateResult(array $data): RouteResult
    {
        $route = $data['routes'][0];
        $distanceMeters = (float) ($route['distance'] ?? 0.0);
        $distanceKm = round($distanceMeters / 1000.0, 1);
        $durationSeconds = (float) ($route['duration'] ?? 0.0);
        $durationMinutes = (int) round($durationSeconds / 60.0);
        $geometry = $route['geometry'] ?? ['type' => 'LineString', 'coordinates' => []];

        $legs = [];
        if (!empty($route['legs']) && is_array($route['legs'])) {
            foreach ($route['legs'] as $idx => $leg) {
                $legDistKm = round(((float) ($leg['distance'] ?? 0.0)) / 1000.0, 1);
                $legDurMin = (int) round(((float) ($leg['duration'] ?? 0.0)) / 60.0);
                $legs[] = [
                    'distanceKm' => $legDistKm,
                    'durationMinutes' => $legDurMin,
                    'durationFormatted' => self::formatDuration($legDurMin),
                    'fromIndex' => $idx,
                    'toIndex' => $idx + 1,
                ];
            }
        }

        return new RouteResult(
            distanceKm: $distanceKm,
            durationMinutes: $durationMinutes,
            durationFormatted: self::formatDuration($durationMinutes),
            geometry: $geometry,
            legs: $legs,
            fromFallback: false,
            raw: $data
        );
    }

    /**
     * Calculate route using Moroccan road matrix and Haversine formula fallback.
     *
     * @param array<int, array{lat: float, lon: float, cityName?: string}> $waypoints
     */
    private function calculateFallbackRoute(array $waypoints): RouteResult
    {
        $totalDistance = 0.0;
        $totalMinutes = 0;
        $legs = [];
        $coordinates = [];

        for ($i = 0; $i < count($waypoints) - 1; $i++) {
            $from = $waypoints[$i];
            $to = $waypoints[$i + 1];

            $fromCity = mb_strtolower(trim($from['cityName'] ?? ''), 'UTF-8');
            $toCity = mb_strtolower(trim($to['cityName'] ?? ''), 'UTF-8');
            $matrixKey = $fromCity . '_' . $toCity;

            if (isset(self::MOROCCAN_ROAD_MATRIX[$matrixKey])) {
                $legDist = self::MOROCCAN_ROAD_MATRIX[$matrixKey]['distanceKm'];
                $legDur = self::MOROCCAN_ROAD_MATRIX[$matrixKey]['durationMinutes'];
            } else {
                // Haversine formula * road winding factor
                $crowFliesKm = $this->calculateHaversineDistance($from['lat'], $from['lon'], $to['lat'], $to['lon']);
                $legDist = round($crowFliesKm * self::HIGHWAY_WINDING_FACTOR, 1);
                // Approximate Moroccan average highway/national road speed ~ 80 km/h
                $legDur = max(5, (int) round(($legDist / 80.0) * 60.0));
            }

            $totalDistance += $legDist;
            $totalMinutes += $legDur;

            $legs[] = [
                'distanceKm' => $legDist,
                'durationMinutes' => $legDur,
                'durationFormatted' => self::formatDuration($legDur),
                'fromIndex' => $i,
                'toIndex' => $i + 1,
            ];

            // Build GeoJSON coordinate line segment with interpolated curvature
            $coordinates[] = [$from['lon'], $from['lat']];
            $midLon = ($from['lon'] + $to['lon']) / 2.0;
            $midLat = ($from['lat'] + $to['lat']) / 2.0;
            $coordinates[] = [$midLon, $midLat];
        }

        // Add final point
        $last = end($waypoints);
        $coordinates[] = [$last['lon'], $last['lat']];

        $geometry = [
            'type' => 'LineString',
            'coordinates' => $coordinates,
        ];

        return new RouteResult(
            distanceKm: round($totalDistance, 1),
            durationMinutes: $totalMinutes,
            durationFormatted: self::formatDuration($totalMinutes),
            geometry: $geometry,
            legs: $legs,
            fromFallback: true,
            raw: ['fallback_waypoints' => count($waypoints)]
        );
    }

    /**
     * Normalize waypoints into uniform ['lat' => float, 'lon' => float] arrays.
     *
     * @param array<mixed> $waypoints
     * @return array<int, array{lat: float, lon: float, cityName?: string}>
     */
    private function normalizeWaypoints(array $waypoints): array
    {
        $normalized = [];

        foreach ($waypoints as $wp) {
            if (!is_array($wp)) {
                continue;
            }

            $lat = null;
            $lon = null;
            $cityName = $wp['cityName'] ?? null;

            if (isset($wp['lat']) && (isset($wp['lon']) || isset($wp['lng']))) {
                $lat = (float) $wp['lat'];
                $lon = (float) ($wp['lon'] ?? $wp['lng']);
            } elseif (isset($wp[0], $wp[1])) {
                // If index 0 is > index 1 in Morocco, 0 is lat (e.g. 30.4) and 1 is lon (e.g. -9.5)
                // Otherwise 0 is lon and 1 is lat
                $val0 = (float) $wp[0];
                $val1 = (float) $wp[1];
                if ($val0 > 20.0 && $val1 < 0.0) {
                    $lat = $val0;
                    $lon = $val1;
                } else {
                    $lon = $val0;
                    $lat = $val1;
                }
            }

            if ($lat !== null && $lon !== null) {
                $entry = ['lat' => $lat, 'lon' => $lon];
                if ($cityName !== null) {
                    $entry['cityName'] = (string) $cityName;
                }
                $normalized[] = $entry;
            }
        }

        return $normalized;
    }

    /**
     * Great-circle distance between two points in kilometers.
     */
    private function calculateHaversineDistance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadiusKm = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2) * sin($dLat / 2) +
            cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
            sin($dLon / 2) * sin($dLon / 2);

        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));

        return $earthRadiusKm * $c;
    }
}
