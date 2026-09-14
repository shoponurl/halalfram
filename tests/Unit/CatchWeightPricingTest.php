<?php

declare(strict_types=1);

use App\Support\CatchWeightPricing;
use App\Support\Weight;

it('prices a line as price-per-lb times weight, rounded half-up to the cent', function () {
    // 349 cents/lb × 3.5 lb = 1221.5 → 1222
    expect(CatchWeightPricing::lineCents(349, Weight::pounds('3.500')))->toBe(1222)
        // 100 × 1.005 = 100.5 → 101 (half-up, not banker's rounding)
        ->and(CatchWeightPricing::lineCents(100, Weight::pounds('1.005')))->toBe(101)
        ->and(CatchWeightPricing::lineCents(349, Weight::zero()))->toBe(0);
});

it('applies a percentage on top of cents, rounded half-up', function () {
    // 1222 × 1.10 = 1344.2 → 1344
    expect(CatchWeightPricing::applyPercent(1222, '10'))->toBe(1344)
        // 1222 × 0.90 (a negative percent) = 1099.8 → 1100
        ->and(CatchWeightPricing::applyPercent(1222, '-10'))->toBe(1100)
        ->and(CatchWeightPricing::applyPercent(1000, '0'))->toBe(1000);
});

it('rejects a non-numeric percentage', function () {
    expect(fn () => CatchWeightPricing::applyPercent(1000, 'ten'))->toThrow(InvalidArgumentException::class);
});
