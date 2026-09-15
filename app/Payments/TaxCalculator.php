<?php

declare(strict_types=1);

namespace App\Payments;

/**
 * Guideline task (S06): "Stripe Tax". Threaded through the estimate, hold and final total (the same
 * way delivery_fee_cents is, per the regression Sprint 05 found) so it's ready to switch on — but kept
 * at zero by default. PA's food/grocery sales-tax exemption needs the owner's determination before any
 * real rate goes live (the same class of gate as the Sprint 02 USDA question); wiring Stripe Tax's
 * actual Tax Calculation API is next once that answer is in.
 */
interface TaxCalculator
{
    public function calculate(int $taxableCents): int;
}
