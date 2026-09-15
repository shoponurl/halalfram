<?php

declare(strict_types=1);

namespace App\Payments;

/** Default until the owner's PA food-tax determination lands — see TaxCalculator's docblock. */
final class NullTaxCalculator implements TaxCalculator
{
    public function calculate(int $taxableCents): int
    {
        return 0;
    }
}
