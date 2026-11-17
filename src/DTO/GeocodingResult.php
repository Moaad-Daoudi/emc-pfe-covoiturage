<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Result DTO representing geocoded or reverse-geocoded coordinates from Nominatim OpenStreetMap.
 */
final readonly class GeocodingResult
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public float $latitude,
        public float $longitude,
        public string $displayName,
        public ?string $city = null,
        public ?string $country = null,
        public ?string $postcode = null,
        public bool $fromFallback = false,
        public array $raw = []
    ) {
    }

    /**
     * @return array{
     *     latitude: float,
     *     longitude: float,
     *     displayName: string,
     *     city: ?string,
     *     country: ?string,
     *     postcode: ?string,
     *     fromFallback: bool
     * }
     */
    public function toArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'displayName' => $this->displayName,
            'city' => $this->city,
            'country' => $this->country,
            'postcode' => $this->postcode,
            'fromFallback' => $this->fromFallback,
        ];
    }
}
