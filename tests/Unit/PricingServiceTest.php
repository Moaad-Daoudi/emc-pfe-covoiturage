<?php

declare(strict_types=1);

namespace App\Tests\Unit;

use App\DTO\PricingBreakdown;
use App\Service\PricingService;
use PHPUnit\Framework\TestCase;

class PricingServiceTest extends TestCase
{
    private PricingService $pricingService;

    protected function setUp(): void
    {
        $this->pricingService = new PricingService();
    }

    public function testCalculateSuggestedPriceForTypicalMoroccanRoutes(): void
    {
        // Agadir -> Marrakech (~230 km on Autoroute A3)
        $breakdown = $this->pricingService->calculateSuggestedPrice(230.0);

        $this->assertInstanceOf(PricingBreakdown::class, $breakdown);
        $this->assertSame(230.0, $breakdown->distanceKm);
        // Suggested price should be rounded to a multiple of 5 MAD
        $this->assertSame(0, $breakdown->suggestedPrice % 5);
        // Formula: (10 + (230 * 0.42)) * 0.90 = (10 + 96.6) * 0.90 = 106.6 * 0.90 = 95.94 -> round to 5 = 95 or 100 MAD
        $this->assertGreaterThanOrEqual(90, $breakdown->suggestedPrice);
        $this->assertLessThanOrEqual(130, $breakdown->suggestedPrice);

        // Verification of min and max bounds
        $this->assertLessThanOrEqual($breakdown->suggestedPrice, $breakdown->minPrice);
        $this->assertGreaterThanOrEqual($breakdown->suggestedPrice, $breakdown->maxPrice);
        $this->assertGreaterThan(0, $breakdown->estimatedFuelCost);
    }

    public function testMinimumPriceConstraint(): void
    {
        // Very short 5 km ride
        $breakdown = $this->pricingService->calculateSuggestedPrice(5.0);

        $this->assertSame(PricingService::MINIMUM_PRICE, $breakdown->suggestedPrice);
        $this->assertSame(PricingService::MINIMUM_PRICE, $breakdown->minPrice);
    }

    public function testMultiSegmentBreakdown(): void
    {
        $segments = [
            ['fromCity' => 'Agadir', 'toCity' => 'Chichaoua', 'distanceKm' => 165.0],
            ['fromCity' => 'Chichaoua', 'toCity' => 'Marrakech', 'distanceKm' => 85.0],
        ];

        $breakdown = $this->pricingService->calculateMultiSegmentBreakdown(250.0, $segments);

        $this->assertCount(2, $breakdown->segmentBreakdowns);

        $seg1 = $breakdown->segmentBreakdowns[0];
        $this->assertSame('Agadir', $seg1['fromCity']);
        $this->assertSame('Chichaoua', $seg1['toCity']);
        $this->assertSame(165.0, $seg1['distanceKm']);
        $this->assertGreaterThanOrEqual(60, $seg1['suggestedPrice']);

        $seg2 = $breakdown->segmentBreakdowns[1];
        $this->assertSame('Chichaoua', $seg2['fromCity']);
        $this->assertSame('Marrakech', $seg2['toCity']);
        $this->assertSame(85.0, $seg2['distanceKm']);
        $this->assertGreaterThanOrEqual(40, $seg2['suggestedPrice']);
    }

    public function testPriceComplianceValidation(): void
    {
        $distance = 230.0; // Agadir -> Marrakech

        // Under minimum (e.g. 15 MAD) -> rejected
        $this->assertFalse($this->pricingService->isPriceCompliant(15, $distance));

        // Normal fair price (e.g. 110 MAD) -> accepted
        $this->assertTrue($this->pricingService->isPriceCompliant(110, $distance));

        // Abusive / commercial price (e.g. 350 MAD for 230 km -> rate > 0.65 MAD/km) -> rejected
        $this->assertFalse($this->pricingService->isPriceCompliant(350, $distance));
    }
}
