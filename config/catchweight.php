<?php

declare(strict_types=1);

/*
 * Catch-weight payment policy — owner decisions for guideline ch. 7, S01 (recorded 2026-09-14).
 * Every order snapshots these values when it is placed, so changing them never alters existing orders.
 */
return [

    // Card hold = estimated total × (1 + hold_tolerance_pct / 100)
    'hold_tolerance_pct' => 10,

    // Actual total above the hold: charge the saved card automatically while the actual total is
    // at most estimate × (1 + overage_autocharge_pct / 100); above that, send a payment link instead.
    'overage_autocharge_pct' => 25,

    // Actual total more than this % BELOW the estimate waits for a manager's approval before capture
    // (catches weight-entry mistakes). Within the threshold the lower amount is captured automatically.
    'underweight_review_pct' => 20,

    // Stripe will not process card amounts under this; smaller balances are written off and logged.
    'minimum_charge_cents' => 50,

    // Online customer-initiated card holds last 7 days on Visa/Mastercard/Amex/Discover (Stripe docs,
    // checked 2026-09-14). The real deadline comes from the charge's `capture_before`; this is the fallback.
    'authorization_fallback_days' => 7,

    'currency' => 'usd',
];
