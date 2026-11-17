<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * Result DTO representing fair, algorithm-suggested pricing complying with Moroccan ADM barème.
 */
final readonly class PricingBreakdown
{
    /**
     * @param array<int, array{
     *     fromCity: string,
     *     toCity: string,
     *     distanceKm: float,
     *     suggestedPrice: int,
     *     minPrice: int,
     *     maxPrice: int
     * }> $segmentBreakdowns
     */
    public function __construct(
        public float $distanceKm,
        public int $suggestedPrice,
        public int $minPrice,
        public int $maxPrice,
        public float $ratePerKm,
        public float $degressiveFactor,
        public int $estimatedFuelCost,
        public int $estimatedTollCost,
        public array $segmentBreakdowns = []
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'distanceKm' => $this->distanceKm,
            'suggestedPrice' => $this->suggestedPrice,
            'minPrice' => $this->minPrice,
            'maxPrice' => $this->maxPrice,
            'ratePerKm' => $this->ratePerKm,
            'degressiveFactor' => $this->degressiveFactor,
            'estimatedFuelCost' => $this->estimatedFuelCost,
            'estimatedTollCost' => $this->estimatedTollCost,
            'segmentBreakdowns' => $this->segmentBreakdowns,
        ];
    }
}
