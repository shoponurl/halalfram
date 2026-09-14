<?php

declare(strict_types=1);

namespace App\Support;

/**
 * What to do with the card hold once every item is weighed.
 * Pure data, built by SettlementPlan::for() from the order's snapshot of the S01 policy.
 */
final readonly class SettlementPlan
{
    public const REVIEW = 'needs_review';

    public const CAPTURE = 'capture';                       // capture ≤ hold, release the rest

    public const CAPTURE_AND_CHARGE = 'capture_and_charge'; // capture hold + off-session charge for the difference

    public const CAPTURE_AND_LINK = 'capture_and_link';     // capture hold + payment link for the difference

    public const CANCEL = 'cancel';                         // nothing (or less than the card minimum) to capture

    private function __construct(
        public string $action,
        public int $finalCents,
        public int $captureCents,
        public int $balanceCents,
        public int $writeOffCents,
        public string $reason,
    ) {}

    /**
     * @param  int  $estimatedCents  what the customer saw at checkout
     * @param  int  $holdCents  authorized on the card (estimate + tolerance)
     * @param  int  $finalCents  sum of actual-weight line prices
     */
    public static function for(
        int $estimatedCents,
        int $holdCents,
        int $finalCents,
        string $overageAutochargePct,
        string $underweightReviewPct,
        bool $underweightApproved,
        int $minimumChargeCents,
    ): self {
        $reviewFloor = CatchWeightPricing::applyPercent($estimatedCents, '-'.$underweightReviewPct);
        if ($finalCents < $reviewFloor && ! $underweightApproved) {
            return new self(self::REVIEW, $finalCents, 0, 0, 0,
                "Actual total is more than {$underweightReviewPct}% below the estimate — a manager must confirm the weights.");
        }

        if ($finalCents < $minimumChargeCents) {
            return new self(self::CANCEL, $finalCents, 0, 0, $finalCents,
                'Actual total is below the card minimum; the hold is released and the amount written off.');
        }

        if ($finalCents <= $holdCents) {
            return new self(self::CAPTURE, $finalCents, $finalCents, 0, 0,
                'Actual total is within the hold; capture it and release the rest.');
        }

        $difference = $finalCents - $holdCents;
        if ($difference < $minimumChargeCents) {
            return new self(self::CAPTURE, $finalCents, $holdCents, 0, $difference,
                'Overage is below the card minimum; capture the hold and write off the difference.');
        }

        $autochargeCeiling = CatchWeightPricing::applyPercent($estimatedCents, $overageAutochargePct);
        if ($finalCents <= $autochargeCeiling) {
            return new self(self::CAPTURE_AND_CHARGE, $finalCents, $holdCents, $difference, 0,
                "Actual total is above the hold but within estimate +{$overageAutochargePct}%; charge the saved card for the difference.");
        }

        return new self(self::CAPTURE_AND_LINK, $finalCents, $holdCents, $difference, 0,
            "Actual total is more than estimate +{$overageAutochargePct}%; send the customer a payment link for the difference.");
    }
}
