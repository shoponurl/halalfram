<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Sprint 01 catch-weight payment lifecycle, extended by Sprint 04's butcher workflow: once every item
 * is weighed, a QC check gates capture (owner decision S04) — a failed check sends it back to
 * QcFailed for re-cutting/re-weighing, never straight to charging the card.
 */
enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';        // order saved, customer hasn't authorized the card yet
    case Authorized = 'authorized';                 // hold placed; waiting for weighing and/or QC
    case QcFailed = 'qc_failed';                     // QC rejected the cut; needs re-cutting and re-weighing
    case QcPassed = 'qc_passed';                     // QC approved; ready to finalize and charge
    case NeedsReview = 'needs_review';              // actual total far below estimate; manager must approve
    case Settling = 'settling';                     // weights locked; capture/charges running on the queue
    case AwaitingBalance = 'awaiting_balance';      // hold captured; payment link sent for the rest
    case AwaitingCashPayment = 'awaiting_cash_payment'; // cash on delivery/pickup: weighed and finalized, cash due at pickup
    case Completed = 'completed';                   // fully paid (or balance written off below the minimum)
    case PaymentFailed = 'payment_failed';
    case AuthorizationExpired = 'authorization_expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Awaiting payment',
            self::Authorized => 'Payment held — ready to weigh',
            self::QcFailed => 'QC failed — needs re-cutting',
            self::QcPassed => 'QC passed — ready to finalize',
            self::NeedsReview => 'Needs manager review',
            self::Settling => 'Charging',
            self::AwaitingBalance => 'Awaiting balance payment',
            self::AwaitingCashPayment => 'Awaiting cash payment',
            self::Completed => 'Completed',
            self::PaymentFailed => 'Payment failed',
            self::AuthorizationExpired => 'Hold expired',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Authorized, self::Settling, self::QcPassed => 'info',
            self::NeedsReview, self::AwaitingBalance, self::AwaitingCashPayment, self::PendingPayment, self::QcFailed => 'warning',
            self::Completed => 'success',
            self::PaymentFailed, self::AuthorizationExpired, self::Cancelled => 'danger',
        };
    }

    /** Weights can still be recorded or corrected — including after a failed QC check. */
    public function acceptsWeights(): bool
    {
        return in_array($this, [self::Authorized, self::QcFailed, self::NeedsReview], true);
    }

    /** All items are weighed and a QC check (pass or fail) can be recorded. */
    public function acceptsQcCheck(): bool
    {
        return in_array($this, [self::Authorized, self::QcFailed], true);
    }

    /**
     * An order that will never be fulfilled no longer holds a place in any capacity/slot count.
     *
     * @return list<self>
     */
    public static function abandoned(): array
    {
        return [self::PaymentFailed, self::AuthorizationExpired, self::Cancelled];
    }
}
