<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Delivery/pickup lifecycle (guideline ch. 6, Sprint 05) — a separate track from the payment
 * OrderStatus. Starts once the order is paid (Settling onward) and runs independently of it.
 */
enum FulfilmentStatus: string
{
    case AwaitingFulfilment = 'awaiting_fulfilment';   // paid, not yet ready/dispatched
    case ReadyForPickup = 'ready_for_pickup';
    case PickedUp = 'picked_up';
    case MissedPickup = 'missed_pickup';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case DeliveryFailed = 'delivery_failed';          // first attempt failed; a re-attempt is still allowed
    case Returned = 'returned';                        // second attempt failed; item is back at the shop
    case Refunded = 'refunded';                        // a missed pickup/delivery refund was issued
    case Shipped = 'shipped';                           // guideline ch. 6, Sprint 08: carrier label bought, in transit
    case ArrivedWarm = 'arrived_warm';                  // cold-chain failure reported; a full refund follows (owner decision S08)

    public function label(): string
    {
        return match ($this) {
            self::AwaitingFulfilment => 'Preparing',
            self::ReadyForPickup => 'Ready for pickup',
            self::PickedUp => 'Picked up',
            self::MissedPickup => 'Missed pickup',
            self::OutForDelivery => 'Out for delivery',
            self::Delivered => 'Delivered',
            self::DeliveryFailed => 'Delivery attempt failed',
            self::Returned => 'Returned to shop',
            self::Refunded => 'Refunded',
            self::Shipped => 'Shipped',
            self::ArrivedWarm => 'Arrived warm — refunded',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::AwaitingFulfilment, self::ReadyForPickup, self::OutForDelivery, self::Shipped => 'info',
            self::PickedUp, self::Delivered => 'success',
            self::DeliveryFailed, self::MissedPickup => 'warning',
            self::Returned, self::Refunded, self::ArrivedWarm => 'danger',
        };
    }

    /** A re-delivery attempt or pickup can still happen. */
    public function isOpenForRetry(): bool
    {
        return in_array($this, [self::ReadyForPickup, self::OutForDelivery, self::DeliveryFailed], true);
    }
}
