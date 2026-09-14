<?php

declare(strict_types=1);

use App\Support\SettlementPlan;

/*
 * Owner policy S01 (2026-09-14): 10% hold tolerance, auto-charge up to estimate +25%,
 * manager review below estimate −20%. Every branch of the decision table is covered here;
 * SettleOrderPaymentTest exercises the same branches against Stripe.
 */
function settlementPlanFor(int $estimated, int $hold, int $final, bool $underweightApproved = false, int $minChargeCents = 50): SettlementPlan
{
    return SettlementPlan::for($estimated, $hold, $final, overageAutochargePct: '25', underweightReviewPct: '20', underweightApproved: $underweightApproved, minimumChargeCents: $minChargeCents);
}

it('captures the actual total when it is within the hold', function () {
    // estimate 1200, hold 1320 (×1.10), actual 1300 ≤ hold
    $result = settlementPlanFor(1200, 1320, 1300);

    expect($result->action)->toBe(SettlementPlan::CAPTURE)
        ->and($result->captureCents)->toBe(1300)
        ->and($result->balanceCents)->toBe(0)
        ->and($result->writeOffCents)->toBe(0);
});

it('captures exactly the hold when the actual total equals the hold', function () {
    $result = settlementPlanFor(1200, 1320, 1320);

    expect($result->action)->toBe(SettlementPlan::CAPTURE)->and($result->captureCents)->toBe(1320);
});

it('auto-charges the difference when the overage is above the hold but within estimate +25%', function () {
    // estimate 1200 → ceiling 1500 (×1.25); actual 1450 is above hold (1320) but ≤ ceiling
    $result = settlementPlanFor(1200, 1320, 1450);

    expect($result->action)->toBe(SettlementPlan::CAPTURE_AND_CHARGE)
        ->and($result->captureCents)->toBe(1320)
        ->and($result->balanceCents)->toBe(130)
        ->and($result->finalCents)->toBe(1450);
});

it('sends a payment link when the actual total is above estimate +25%', function () {
    // ceiling 1500; actual 1600 is above it
    $result = settlementPlanFor(1200, 1320, 1600);

    expect($result->action)->toBe(SettlementPlan::CAPTURE_AND_LINK)
        ->and($result->captureCents)->toBe(1320)
        ->and($result->balanceCents)->toBe(280);
});

it('treats an overage right at the +25% ceiling as auto-charge, not a link', function () {
    $result = settlementPlanFor(1200, 1320, 1500);

    expect($result->action)->toBe(SettlementPlan::CAPTURE_AND_CHARGE);
});

it('writes off an overage smaller than the card minimum instead of an extra charge', function () {
    $result = settlementPlanFor(1200, 1320, 1349);   // +29¢ over the hold

    expect($result->action)->toBe(SettlementPlan::CAPTURE)
        ->and($result->captureCents)->toBe(1320)
        ->and($result->writeOffCents)->toBe(29);
});

it('needs manager review when the actual total is more than 20% below the estimate, unapproved', function () {
    // floor = 1200 × 0.80 = 960; actual 900 is below it
    $result = settlementPlanFor(1200, 1320, 900);

    expect($result->action)->toBe(SettlementPlan::REVIEW);
});

it('proceeds once underweight is approved, capturing only the actual amount', function () {
    $result = settlementPlanFor(1200, 1320, 900, underweightApproved: true);

    expect($result->action)->toBe(SettlementPlan::CAPTURE)->and($result->captureCents)->toBe(900);
});

it('does not require review for underweight within 20%, even unapproved', function () {
    // floor 960; actual 1000 is above it
    $result = settlementPlanFor(1200, 1320, 1000);

    expect($result->action)->toBe(SettlementPlan::CAPTURE)->and($result->captureCents)->toBe(1000);
});

it('cancels the hold and writes everything off when the actual total is below the card minimum', function () {
    $result = settlementPlanFor(1200, 1320, 40, underweightApproved: true);

    expect($result->action)->toBe(SettlementPlan::CANCEL)
        ->and($result->captureCents)->toBe(0)
        ->and($result->writeOffCents)->toBe(40);
});

it('always explains its decision', function () {
    expect(settlementPlanFor(1200, 1320, 1300)->reason)->not->toBeEmpty();
});
