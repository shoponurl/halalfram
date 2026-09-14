<?php

declare(strict_types=1);

namespace App\Support;

use InvalidArgumentException;

/**
 * Integer-cent pricing maths for catch-weight items. No floats anywhere (rule 01):
 * all multiplication runs through bcmath and is rounded half-up to whole cents exactly once.
 */
final class CatchWeightPricing
{
    /** Price of a weight at a per-lb price, rounded half-up to the cent. */
    public static function lineCents(int $pricePerLbCents, Weight $weight): int
    {
        if ($pricePerLbCents < 0 || ! $weight->isPositive()) {
            return 0;
        }

        return self::roundToCents(bcmul((string) $pricePerLbCents, $weight->toDecimal(), 6));
    }

    /** estimate × (1 + pct/100), rounded half-up. Used for the hold and the review/auto-charge thresholds. */
    public static function applyPercent(int $cents, string $percent): int
    {
        if (! is_numeric($percent)) {
            throw new InvalidArgumentException("Invalid percentage [{$percent}].");
        }

        return self::roundToCents(bcdiv(bcmul((string) $cents, bcadd('100', $percent, 4), 6), '100', 6));
    }

    /** @param numeric-string $value */
    private static function roundToCents(string $value): int
    {
        return (int) bcadd($value, '0.5', 0);
    }
}
