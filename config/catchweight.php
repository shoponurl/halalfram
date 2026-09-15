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

    // Owner decision (guideline ch. 7, S02, recorded 2026-09-14): cut/packing options that add butcher
    // processing time are capped so a real order can never need longer than the hold stays valid.
    // Kept below authorization_fallback_days to leave a buffer for weighing, QC and capture.
    'max_lead_time_days' => 4,

    'currency' => 'usd',

    // Owner decisions (guideline ch. 7, S04, recorded 2026-09-15):
    // Orders placed after this local time are scheduled starting the next production day.
    'order_cutoff_time' => '15:00',

    // Capacity is measured in butcher-minutes, not weight. A cut option's own estimated_minutes wins;
    // this is the per-piece default for a standard item (no cut option chosen).
    'default_processing_minutes' => 5,

    // The shop's default daily processing budget, in butcher-minutes. Override a specific date via
    // App\Models\ProductionDay (a holiday, extra staff, etc.).
    'daily_capacity_minutes' => 480,

    // A scheduled order can never be pushed further out than the card hold stays valid — no
    // re-authorization flow (same call as the Sprint 02 lead-time cap). Reuses authorization_fallback_days.
];
