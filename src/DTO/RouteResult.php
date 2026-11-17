<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Result DTO representing road routing data from OSRM (Open Source Routing Machine).
 */
final readonly class RouteResult
{
    /**
     * @param array{type: string, coordinates: array<int, array{0: float, 1: float}>} $geometry GeoJSON LineString
     * @param array<int, array{
     *     distanceKm: float,
     *     durationMinutes: int,
     *     durationFormatted: string,
     *     fromIndex: int,
     *     toIndex: int
     * }> $legs
     * @param array<string, mixed> $raw
     */
    public function __construct(
        public float $distanceKm,
        public int $durationMinutes,
        public string $durationFormatted,
        public array $geometry,
        public array $legs = [],
        public bool $fromFallback = false,
        public array $raw = []
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'distanceKm' => $this->distanceKm,
            'durationMinutes' => $this->durationMinutes,
            'durationFormatted' => $this->durationFormatted,
            'geometry' => $this->geometry,
            'legs' => $this->legs,
            'fromFallback' => $this->fromFallback,
        ];
    }
}
