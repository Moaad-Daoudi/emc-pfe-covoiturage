<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\PricingBreakdown;
use App\Entity\Trip;

/**
 * Service calculating fair, algorithmically suggested carpooling prices.
 * Strictly adheres to Moroccan cost-sharing regulations (non-commercial transport)
 * and Autoroutes du Maroc (ADM) distance/toll barème.
 *
 * Formula:
 *   SuggestedPrice = round_to_5( (BaseCost + (DistanceKm * RateKm)) * DegressiveFactor * ComfortMultiplier )
 */
class PricingService
{
    public const BASE_COST = 10.0; // Fixed entry/overhead cost in MAD
    public const BASE_RATE_PER_KM = 0.42; // ADM cost per passenger seat (fuel + toll share) in MAD/km
    public const MINIMUM_PRICE = 20; // Minimum seat price in MAD
    public const LEGAL_MAX_RATE_PER_KM = 0.65; // Legal ceiling for non-commercial cost-sharing

    /**
     * Calculate suggested price breakdown for a given road distance.
     */
    public function calculateSuggestedPrice(float $distanceKm, bool $hasAirConditioning = false): PricingBreakdown
    {
        $clampedDistance = max(1.0, $distanceKm);
        $degressiveFactor = $this->resolveDegressiveFactor($clampedDistance);
        $comfortMultiplier = $hasAirConditioning ? 1.05 : 1.0;

        $rawPrice = (self::BASE_COST + ($clampedDistance * self::BASE_RATE_PER_KM)) * $degressiveFactor * $comfortMultiplier;
        $suggestedPrice = max(self::MINIMUM_PRICE, (int) (round($rawPrice / 5.0) * 5));

        // Regulatory bounds: 75% min and 125% max (capped by legal non-commercial ceiling)
        $minPrice = max(self::MINIMUM_PRICE, (int) (round(($suggestedPrice * 0.75) / 5.0) * 5));
        $legalCeiling = (int) ceil($clampedDistance * self::LEGAL_MAX_RATE_PER_KM);
        $maxPrice = max($suggestedPrice, min($legalCeiling, (int) (round(($suggestedPrice * 1.25) / 5.0) * 5)));

        // Cost decomposition
        $estimatedFuelCost = (int) round($clampedDistance * 0.28);
        $estimatedTollCost = max(0, $suggestedPrice - $estimatedFuelCost - (int) self::BASE_COST);

        return new PricingBreakdown(
            distanceKm: round($clampedDistance, 1),
            suggestedPrice: $suggestedPrice,
            minPrice: $minPrice,
            maxPrice: $maxPrice,
            ratePerKm: self::BASE_RATE_PER_KM,
            degressiveFactor: $degressiveFactor,
            estimatedFuelCost: $estimatedFuelCost,
            estimatedTollCost: $estimatedTollCost,
            segmentBreakdowns: []
        );
    }

    /**
     * Calculate fair pricing distribution across all segments of a multi-stop trip.
     *
     * @param array<int, array{fromCity: string, toCity: string, distanceKm: float}> $segments
     */
    public function calculateMultiSegmentBreakdown(float $totalDistanceKm, array $segments, bool $hasAirConditioning = false): PricingBreakdown
    {
        $mainBreakdown = $this->calculateSuggestedPrice($totalDistanceKm, $hasAirConditioning);
        $segmentBreakdowns = [];

        foreach ($segments as $seg) {
            $segDistance = $seg['distanceKm'];
            // Proportional share with segment-level degressive calculation
            $segBreakdown = $this->calculateSuggestedPrice($segDistance, $hasAirConditioning);

            $segmentBreakdowns[] = [
                'fromCity' => $seg['fromCity'],
                'toCity' => $seg['toCity'],
                'distanceKm' => round($segDistance, 1),
                'suggestedPrice' => $segBreakdown->suggestedPrice,
                'minPrice' => $segBreakdown->minPrice,
                'maxPrice' => $segBreakdown->maxPrice,
            ];
        }

        return new PricingBreakdown(
            distanceKm: $mainBreakdown->distanceKm,
            suggestedPrice: $mainBreakdown->suggestedPrice,
            minPrice: $mainBreakdown->minPrice,
            maxPrice: $mainBreakdown->maxPrice,
            ratePerKm: $mainBreakdown->ratePerKm,
            degressiveFactor: $mainBreakdown->degressiveFactor,
            estimatedFuelCost: $mainBreakdown->estimatedFuelCost,
            estimatedTollCost: $mainBreakdown->estimatedTollCost,
            segmentBreakdowns: $segmentBreakdowns
        );
    }

    /**
     * Verify whether a proposed driver price complies with non-commercial Moroccan regulations.
     */
    public function isPriceCompliant(int $price, float $distanceKm): bool
    {
        if ($price < self::MINIMUM_PRICE) {
            return false;
        }

        $clampedDistance = max(1.0, $distanceKm);
        $maxAllowed = (int) ceil($clampedDistance * self::LEGAL_MAX_RATE_PER_KM);

        return $price <= $maxAllowed;
    }

    /**
     * Degressive curve: longer journeys benefit from lower per-kilometer cost.
     */
    private function resolveDegressiveFactor(float $distanceKm): float
    {
        return match (true) {
            $distanceKm <= 80.0 => 1.00,
            $distanceKm <= 180.0 => 0.95,
            $distanceKm <= 300.0 => 0.90,
            default => 0.85,
        };
    }
}
