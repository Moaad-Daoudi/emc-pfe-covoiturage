<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\DTO\RouteResult;
use App\Entity\City;
use App\Service\OsrmService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class OsrmServiceTest extends TestCase
{
    public function testCalculateRouteRequiresAtLeastTwoWaypoints(): void
    {
        $httpClient = new MockHttpClient();
        $cache = new ArrayAdapter();
        $service = new OsrmService($httpClient, $cache, new NullLogger());

        $this->expectException(\InvalidArgumentException::class);
        $service->calculateRoute([['lat' => 30.4, 'lon' => -9.5]]);
    }

    public function testCalculateRouteWithMockApiResponse(): void
    {
        $mockPayload = json_encode([
            'code' => 'Ok',
            'routes' => [
                [
                    'distance' => 234500.0, // 234.5 km
                    'duration' => 13500.0,  // 225 min -> 3h45
                    'geometry' => [
                        'type' => 'LineString',
                        'coordinates' => [
                            [-9.5981, 30.4278],
                            [-8.7617, 31.5422],
                            [-7.9811, 31.6295],
                        ],
                    ],
                    'legs' => [
                        [
                            'distance' => 165000.0,
                            'duration' => 8100.0, // 135 min
                        ],
                        [
                            'distance' => 69500.0,
                            'duration' => 5400.0, // 90 min
                        ],
                    ],
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $requestCount = 0;
        $httpClient = new MockHttpClient(function ($method, $url) use ($mockPayload, &$requestCount) {
            $requestCount++;
            $this->assertSame('GET', $method);
            // Verify OSRM format: lon,lat;lon,lat
            $this->assertStringContainsString('-9.59810,30.42780', $url);
            $this->assertStringContainsString('-7.98110,31.62950', $url);
            $this->assertStringContainsString('overview=full', $url);
            $this->assertStringContainsString('geometries=geojson', $url);

            return new MockResponse($mockPayload, ['http_code' => 200]);
        });

        $cache = new ArrayAdapter();
        $service = new OsrmService($httpClient, $cache, new NullLogger());

        $waypoints = [
            ['lat' => 30.4278, 'lon' => -9.5981],
            ['lat' => 31.5422, 'lon' => -8.7617],
            ['lat' => 31.6295, 'lon' => -7.9811],
        ];

        // 1st call: makes HTTP request
        $result = $service->calculateRoute($waypoints);

        $this->assertInstanceOf(RouteResult::class, $result);
        $this->assertSame(234.5, $result->distanceKm);
        $this->assertSame(225, $result->durationMinutes);
        $this->assertSame('3h45', $result->durationFormatted);
        $this->assertFalse($result->fromFallback);
        $this->assertCount(2, $result->legs);
        $this->assertSame(165.0, $result->legs[0]['distanceKm']);
        $this->assertSame(135, $result->legs[0]['durationMinutes']);
        $this->assertSame('2h15', $result->legs[0]['durationFormatted']);
        $this->assertSame(1, $requestCount);

        // 2nd call: should be served from Cache!
        $cachedResult = $service->calculateRoute($waypoints);
        $this->assertSame(234.5, $cachedResult->distanceKm);
        $this->assertSame(1, $requestCount, 'Cached route must not make a second HTTP request');
    }

    public function testCalculateRouteFallbackWithKnownMoroccanMatrix(): void
    {
        // Mock failing client to force fallback
        $httpClient = new MockHttpClient(function () {
            throw new \RuntimeException('OSRM API unreachable');
        });
        $cache = new ArrayAdapter();
        $service = new OsrmService($httpClient, $cache, new NullLogger());

        $waypoints = [
            ['lat' => 30.4278, 'lon' => -9.5981, 'cityName' => 'Agadir'],
            ['lat' => 31.6295, 'lon' => -7.9811, 'cityName' => 'Marrakech'],
        ];

        $result = $service->calculateRoute($waypoints);

        $this->assertInstanceOf(RouteResult::class, $result);
        $this->assertTrue($result->fromFallback);
        // Agadir - Marrakech in road matrix: 230 km, 225 min (3h45)
        $this->assertSame(230.0, $result->distanceKm);
        $this->assertSame(225, $result->durationMinutes);
        $this->assertSame('3h45', $result->durationFormatted);
        $this->assertSame('LineString', $result->geometry['type']);
        $this->assertNotEmpty($result->geometry['coordinates']);
    }

    public function testCalculateRouteFallbackMultiLegs(): void
    {
        $httpClient = new MockHttpClient(function () {
            throw new \RuntimeException('Network down');
        });
        $cache = new ArrayAdapter();
        $service = new OsrmService($httpClient, $cache, new NullLogger());

        // Agadir -> Chichaoua (165km, 135min) -> Marrakech (85km, 70min)
        $waypoints = [
            ['lat' => 30.4278, 'lon' => -9.5981, 'cityName' => 'Agadir'],
            ['lat' => 31.5422, 'lon' => -8.7617, 'cityName' => 'Chichaoua'],
            ['lat' => 31.6295, 'lon' => -7.9811, 'cityName' => 'Marrakech'],
        ];

        $result = $service->calculateRoute($waypoints);

        $this->assertTrue($result->fromFallback);
        $this->assertSame(250.0, $result->distanceKm); // 165 + 85 = 250 km
        $this->assertSame(205, $result->durationMinutes); // 135 + 70 = 205 min
        $this->assertSame('3h25', $result->durationFormatted);
        $this->assertCount(2, $result->legs);
        $this->assertSame(165.0, $result->legs[0]['distanceKm']);
        $this->assertSame(85.0, $result->legs[1]['distanceKm']);
    }

    public function testCalculateRouteBetweenCities(): void
    {
        $httpClient = new MockHttpClient(function () {
            throw new \RuntimeException('Offline');
        });
        $cache = new ArrayAdapter();
        $service = new OsrmService($httpClient, $cache, new NullLogger());

        $agadir = (new City())->setName('Agadir')->setLatitude(30.4278)->setLongitude(-9.5981);
        $marrakech = (new City())->setName('Marrakech')->setLatitude(31.6295)->setLongitude(-7.9811);
        $chichaoua = (new City())->setName('Chichaoua')->setLatitude(31.5422)->setLongitude(-8.7617);

        $result = $service->calculateRouteBetweenCities($agadir, $marrakech, [$chichaoua]);

        $this->assertInstanceOf(RouteResult::class, $result);
        $this->assertSame(250.0, $result->distanceKm);
        $this->assertSame(205, $result->durationMinutes);
    }

    public function testFormatDurationHelper(): void
    {
        $this->assertSame('45 min', OsrmService::formatDuration(45));
        $this->assertSame('1h00', OsrmService::formatDuration(60));
        $this->assertSame('1h05', OsrmService::formatDuration(65));
        $this->assertSame('3h45', OsrmService::formatDuration(225));
    }
}
