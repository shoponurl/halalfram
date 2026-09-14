<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Sprint 01 catch-weight payment lifecycle. Sprint 04 extends it with the butcher workflow
 * (Scheduled → Cutting → Packed → QC → Dispatched → Delivered).
 */
enum OrderStatus: string
{
    case PendingPayment = 'pending_payment';        // order saved, customer hasn't authorized the card yet
    case Authorized = 'authorized';                 // hold placed; waiting for weighing
    case NeedsReview = 'needs_review';              // actual total far below estimate; manager must approve
    case Settling = 'settling';                     // weights locked; capture/charges running on the queue
    case AwaitingBalance = 'awaiting_balance';      // hold captured; payment link sent for the rest
    case Completed = 'completed';                   // fully paid (or balance written off below the minimum)
    case PaymentFailed = 'payment_failed';
    case AuthorizationExpired = 'authorization_expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Awaiting payment',
            self::Authorized => 'Payment held — ready to weigh',
            self::NeedsReview => 'Needs manager review',
            self::Settling => 'Charging',
            self::AwaitingBalance => 'Awaiting balance payment',
            self::Completed => 'Completed',
            self::PaymentFailed => 'Payment failed',
            self::AuthorizationExpired => 'Hold expired',
            self::Cancelled => 'Cancelled',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Authorized, self::Settling => 'info',
            self::NeedsReview, self::AwaitingBalance, self::PendingPayment => 'warning',
            self::Completed => 'success',
            self::PaymentFailed, self::AuthorizationExpired, self::Cancelled => 'danger',
        };
    }

    /** Weights can still be recorded or corrected. */
    public function acceptsWeights(): bool
    {
        return in_array($this, [self::Authorized, self::NeedsReview], true);
    }
}
