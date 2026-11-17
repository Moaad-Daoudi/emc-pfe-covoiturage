<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\Service\NominatimService;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class NominatimServiceTest extends TestCase
{
    public function testGeocodeReturnsNullOnEmptyQuery(): void
    {
        $httpClient = new MockHttpClient();
        $cache = new ArrayAdapter();
        $service = new NominatimService($httpClient, $cache, new NullLogger());

        $result = $service->geocode('');
        $this->assertNull($result);

        $resultSpaces = $service->geocode('   ');
        $this->assertNull($resultSpaces);
    }

    public function testGeocodeUsesFallbackDictionaryForMoroccanHubsOnNetworkFailure(): void
    {
        // Mock an HTTP client that throws an exception or fails
        $httpClient = new MockHttpClient(function () {
            throw new \RuntimeException('Nominatim API unreachable or rate limited');
        });
        $cache = new ArrayAdapter();
        $service = new NominatimService($httpClient, $cache, new NullLogger());

        // Test Agadir
        $agadir = $service->geocode('Gare Al Massira, Agadir');
        $this->assertNotNull($agadir);
        $this->assertEqualsWithDelta(30.4278, $agadir->latitude, 0.01);
        $this->assertEqualsWithDelta(-9.5981, $agadir->longitude, 0.01);
        $this->assertTrue($agadir->fromFallback);
        $this->assertSame('Maroc', $agadir->country);

        // Test Marrakech
        $marrakech = $service->geocode('Gare ONCF Marrakech-Guéliz');
        $this->assertNotNull($marrakech);
        $this->assertEqualsWithDelta(31.6295, $marrakech->latitude, 0.01);
        $this->assertEqualsWithDelta(-7.9811, $marrakech->longitude, 0.01);
        $this->assertTrue($marrakech->fromFallback);

        // Test Casablanca
        $casa = $service->geocode('Casablanca Oasis');
        $this->assertNotNull($casa);
        $this->assertEqualsWithDelta(33.5731, $casa->latitude, 0.01);
        $this->assertTrue($casa->fromFallback);
    }

    public function testGeocodeSuccessfulApiResponseAndCaching(): void
    {
        $mockJson = json_encode([
            [
                'lat' => '31.5085',
                'lon' => '-9.7595',
                'display_name' => 'Essaouira, Marrakech-Safi, 44000, Maroc',
                'address' => [
                    'city' => 'Essaouira',
                    'state' => 'Marrakech-Safi',
                    'country' => 'Maroc',
                    'postcode' => '44000',
                ],
            ],
        ], JSON_THROW_ON_ERROR);

        $requestCount = 0;
        $httpClient = new MockHttpClient(function ($method, $url, $options) use ($mockJson, &$requestCount) {
            $requestCount++;
            $this->assertSame('GET', $method);
            $this->assertStringContainsString('q=Essaouira', $url);
            $this->assertStringContainsString('countrycodes=ma', $url);

            // Assert custom User-Agent per OSM Policy
            $headers = $options['headers'] ?? [];
            $foundUserAgent = false;
            foreach ($headers as $header) {
                if (stripos($header, 'User-Agent:') !== false && stripos($header, 'SafarCar') !== false) {
                    $foundUserAgent = true;
                }
            }
            $this->assertTrue($foundUserAgent, 'OSM Nominatim custom User-Agent header must be present.');

            return new MockResponse($mockJson, ['http_code' => 200]);
        });

        $cache = new ArrayAdapter();
        $service = new NominatimService($httpClient, $cache, new NullLogger());

        // First call: calls HTTP API
        $result1 = $service->geocode('Essaouira');
        $this->assertNotNull($result1);
        $this->assertSame(31.5085, $result1->latitude);
        $this->assertSame(-9.7595, $result1->longitude);
        $this->assertSame('Essaouira', $result1->city);
        $this->assertSame('44000', $result1->postcode);
        $this->assertFalse($result1->fromFallback);
        $this->assertSame(1, $requestCount);

        // Second call with same query: must be served from CACHE (zero additional HTTP request)
        $result2 = $service->geocode('Essaouira');
        $this->assertNotNull($result2);
        $this->assertSame(31.5085, $result2->latitude);
        $this->assertSame(1, $requestCount, 'Cached coordinates should not trigger a second HTTP request');
    }

    public function testReverseGeocode(): void
    {
        $mockJson = json_encode([
            'lat' => '30.4278',
            'lon' => '-9.5981',
            'display_name' => 'Avenue Hassan II, Agadir, Souss-Massa, Maroc',
            'address' => [
                'city' => 'Agadir',
                'country' => 'Maroc',
            ],
        ], JSON_THROW_ON_ERROR);

        $httpClient = new MockHttpClient(function ($method, $url) use ($mockJson) {
            $this->assertStringContainsString('/reverse', $url);
            $this->assertStringContainsString('lat=30.4278', $url);
            $this->assertStringContainsString('lon=-9.5981', $url);

            return new MockResponse($mockJson, ['http_code' => 200]);
        });

        $cache = new ArrayAdapter();
        $service = new NominatimService($httpClient, $cache, new NullLogger());

        $result = $service->reverseGeocode(30.4278, -9.5981);
        $this->assertNotNull($result);
        $this->assertSame('Agadir', $result->city);
        $this->assertSame('Avenue Hassan II, Agadir, Souss-Massa, Maroc', $result->displayName);
    }

    public function testGetCoordinatesHelper(): void
    {
        $httpClient = new MockHttpClient(function () {
            throw new \RuntimeException('Offline mode');
        });
        $cache = new ArrayAdapter();
        $service = new NominatimService($httpClient, $cache, new NullLogger());

        $coords = $service->getCoordinates('Chichaoua');
        $this->assertNotNull($coords);
        $this->assertArrayHasKey('lat', $coords);
        $this->assertArrayHasKey('lon', $coords);
        $this->assertEqualsWithDelta(31.5422, $coords['lat'], 0.01);
        $this->assertEqualsWithDelta(-8.7617, $coords['lon'], 0.01);
    }
}
