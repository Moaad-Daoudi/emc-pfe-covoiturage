<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\GeocodingResult;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

/**
 * Service interfacing with OpenStreetMap Nominatim API.
 * Adheres strictly to OSM Usage Policy:
 *  - Custom User-Agent identification
 *  - Cache layer (filesystem/Redis) with configurable TTL
 *  - Resilient offline fallback dictionary for Moroccan cities
 */
class NominatimService
{
    private const DEFAULT_BASE_URL = 'https://nominatim.openstreetmap.org';
    private const DEFAULT_USER_AGENT = 'SafarCar-Morocco-PFE/1.0 (contact@safarcar.ma; +https://safarcar.ma)';
    private const DEFAULT_CACHE_TTL = 2592000; // 30 days in seconds

    /**
     * Fallback dictionary for major Moroccan hubs to ensure zero-downtime resilience
     * during tests, offline demonstrations or Nominatim rate-limiting (429).
     *
     * @var array<string, array{lat: float, lon: float, name: string}>
     */
    private const MOROCCAN_FALLBACK_CITIES = [
        'agadir' => ['lat' => 30.4278, 'lon' => -9.5981, 'name' => 'Agadir, Souss-Massa, Maroc'],
        'marrakech' => ['lat' => 31.6295, 'lon' => -7.9811, 'name' => 'Marrakech, Marrakech-Safi, Maroc'],
        'casablanca' => ['lat' => 33.5731, 'lon' => -7.5898, 'name' => 'Casablanca, Casablanca-Settat, Maroc'],
        'rabat' => ['lat' => 34.0209, 'lon' => -6.8416, 'name' => 'Rabat, Rabat-Salé-Kénitra, Maroc'],
        'essaouira' => ['lat' => 31.5085, 'lon' => -9.7595, 'name' => 'Essaouira, Marrakech-Safi, Maroc'],
        'chichaoua' => ['lat' => 31.5422, 'lon' => -8.7617, 'name' => 'Chichaoua, Marrakech-Safi, Maroc'],
        'tanger' => ['lat' => 35.7595, 'lon' => -5.8340, 'name' => 'Tanger, Tanger-Tétouan-Al Hoceïma, Maroc'],
        'tangier' => ['lat' => 35.7595, 'lon' => -5.8340, 'name' => 'Tanger, Tanger-Tétouan-Al Hoceïma, Maroc'],
        'fes' => ['lat' => 34.0181, 'lon' => -5.0078, 'name' => 'Fès, Fès-Meknès, Maroc'],
        'fez' => ['lat' => 34.0181, 'lon' => -5.0078, 'name' => 'Fès, Fès-Meknès, Maroc'],
        'meknes' => ['lat' => 33.8938, 'lon' => -5.5516, 'name' => 'Meknès, Fès-Meknès, Maroc'],
        'ouarzazate' => ['lat' => 30.9335, 'lon' => -6.9370, 'name' => 'Ouarzazate, Drâa-Tafilalet, Maroc'],
        'el jadida' => ['lat' => 33.2316, 'lon' => -8.5007, 'name' => 'El Jadida, Casablanca-Settat, Maroc'],
        'kenitra' => ['lat' => 34.2610, 'lon' => -6.5802, 'name' => 'Kénitra, Rabat-Salé-Kénitra, Maroc'],
        'safi' => ['lat' => 32.2994, 'lon' => -9.2372, 'name' => 'Safi, Marrakech-Safi, Maroc'],
        'taroudant' => ['lat' => 30.4703, 'lon' => -8.8770, 'name' => 'Taroudant, Souss-Massa, Maroc'],
        'tiznit' => ['lat' => 29.6974, 'lon' => -9.7316, 'name' => 'Tiznit, Souss-Massa, Maroc'],
        'berrechid' => ['lat' => 33.2655, 'lon' => -7.5875, 'name' => 'Berrechid, Casablanca-Settat, Maroc'],
        'settat' => ['lat' => 33.0010, 'lon' => -7.6166, 'name' => 'Settat, Casablanca-Settat, Maroc'],
        'benguerir' => ['lat' => 32.0552, 'lon' => -7.9547, 'name' => 'Ben Guerir, Marrakech-Safi, Maroc'],
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
     * Geocode an address, landmark, or city name into geographic coordinates.
     *
     * @param string $query Query string (e.g. "Gare Al Massira, Agadir" or "Marrakech")
     * @param string|null $countryCode ISO country code constraint ('ma' by default for Morocco)
     * @return GeocodingResult|null Structured result or null if unresolved
     */
    public function geocode(string $query, ?string $countryCode = 'ma'): ?GeocodingResult
    {
        $normalizedQuery = trim($query);
        if ($normalizedQuery === '') {
            return null;
        }

        $cacheKey = sprintf(
            'nominatim_geo_%s_%s',
            md5(mb_strtolower($normalizedQuery, 'UTF-8')),
            $countryCode ?? 'global'
        );

        try {
            $cachedData = $this->cache->get($cacheKey, function (ItemInterface $item) use ($normalizedQuery, $countryCode): ?array {
                $item->expiresAfter($this->cacheTtl);

                return $this->fetchFromNominatimApi($normalizedQuery, $countryCode);
            });

            if ($cachedData !== null) {
                return $this->hydrateResult($cachedData);
            }
        } catch (Throwable $e) {
            $this->logger->warning('Nominatim geocoding failed, falling back to local dictionary', [
                'query' => $normalizedQuery,
                'error' => $e->getMessage(),
            ]);
        }

        // Resilient fallback to local Moroccan hub coordinates
        return $this->resolveFallbackCity($normalizedQuery);
    }

    /**
     * Reverse geocode geographic coordinates into a human-readable address.
     *
     * @param float $latitude Latitude in WGS84
     * @param float $longitude Longitude in WGS84
     * @return GeocodingResult|null
     */
    public function reverseGeocode(float $latitude, float $longitude): ?GeocodingResult
    {
        $cacheKey = sprintf(
            'nominatim_rev_%s_%s',
            str_replace('.', '_', number_format($latitude, 4, '.', '')),
            str_replace('.', '_', number_format($longitude, 4, '.', ''))
        );

        try {
            $cachedData = $this->cache->get($cacheKey, function (ItemInterface $item) use ($latitude, $longitude): ?array {
                $item->expiresAfter($this->cacheTtl);

                $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/') . '/reverse', [
                    'headers' => [
                        'User-Agent' => $this->userAgent,
                        'Accept' => 'application/json',
                    ],
                    'query' => [
                        'lat' => (string) $latitude,
                        'lon' => (string) $longitude,
                        'format' => 'jsonv2',
                        'addressdetails' => 1,
                    ],
                    'timeout' => 4.0,
                ]);

                if ($response->getStatusCode() !== 200) {
                    return null;
                }

                $data = $response->toArray(false);
                if (empty($data) || isset($data['error'])) {
                    return null;
                }

                return $data;
            });

            if ($cachedData !== null) {
                return $this->hydrateResult($cachedData);
            }
        } catch (Throwable $e) {
            $this->logger->warning('Nominatim reverse geocoding error', [
                'lat' => $latitude,
                'lon' => $longitude,
                'error' => $e->getMessage(),
            ]);
        }

        return null;
    }

    /**
     * Quick helper to retrieve coordinates array [lat, lon] for a city name.
     *
     * @return array{lat: float, lon: float}|null
     */
    public function getCoordinates(string $cityName): ?array
    {
        $result = $this->geocode($cityName);
        if ($result === null) {
            return null;
        }

        return [
            'lat' => $result->latitude,
            'lon' => $result->longitude,
        ];
    }

    /**
     * Fetch raw data from the Nominatim REST API.
     *
     * @return array<string, mixed>|null
     */
    private function fetchFromNominatimApi(string $query, ?string $countryCode): ?array
    {
        $queryParams = [
            'q' => $query,
            'format' => 'jsonv2',
            'addressdetails' => 1,
            'limit' => 1,
        ];

        if ($countryCode !== null && $countryCode !== '') {
            $queryParams['countrycodes'] = strtolower($countryCode);
        }

        $response = $this->httpClient->request('GET', rtrim($this->baseUrl, '/') . '/search', [
            'headers' => [
                'User-Agent' => $this->userAgent,
                'Accept' => 'application/json',
            ],
            'query' => $queryParams,
            'timeout' => 4.0,
        ]);

        if ($response->getStatusCode() !== 200) {
            return null;
        }

        $data = $response->toArray(false);
        if (empty($data) || !isset($data[0])) {
            return null;
        }

        return $data[0];
    }

    /**
     * Hydrate a GeocodingResult from API response payload.
     *
     * @param array<string, mixed> $data
     */
    private function hydrateResult(array $data, bool $fromFallback = false): GeocodingResult
    {
        $address = $data['address'] ?? [];
        $city = $address['city']
            ?? $address['town']
            ?? $address['village']
            ?? $address['municipality']
            ?? $address['county']
            ?? null;

        return new GeocodingResult(
            latitude: (float) ($data['lat'] ?? 0.0),
            longitude: (float) ($data['lon'] ?? 0.0),
            displayName: (string) ($data['display_name'] ?? ''),
            city: $city,
            country: $address['country'] ?? 'Maroc',
            postcode: $address['postcode'] ?? null,
            fromFallback: $fromFallback,
            raw: $data
        );
    }

    /**
     * Search the internal dictionary of known Moroccan hubs.
     */
    private function resolveFallbackCity(string $query): ?GeocodingResult
    {
        $normalized = mb_strtolower(trim($query), 'UTF-8');
        // Strip common prefixes
        $cleaned = preg_replace('/^(gare|station|centre-ville|aeroport|port)\s+(de\s+|d\')?/i', '', $normalized);
        $cleaned = trim($cleaned ?? $normalized);

        foreach (self::MOROCCAN_FALLBACK_CITIES as $cityKey => $hub) {
            if (str_contains($normalized, $cityKey) || str_contains($cleaned, $cityKey)) {
                return new GeocodingResult(
                    latitude: $hub['lat'],
                    longitude: $hub['lon'],
                    displayName: $hub['name'],
                    city: ucfirst($cityKey),
                    country: 'Maroc',
                    fromFallback: true,
                    raw: ['fallback_key' => $cityKey]
                );
            }
        }

        return null;
    }
}
