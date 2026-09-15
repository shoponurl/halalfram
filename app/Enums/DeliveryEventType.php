<?php

declare(strict_types=1);

namespace App\Enums;

/** Append-only fulfilment ledger (rule 04, guideline ch. 6 Sprint 05). */
enum DeliveryEventType: string
{
    case ReadyForPickup = 'ready_for_pickup';
    case PickedUp = 'picked_up';
    case PickupReminderSent = 'pickup_reminder_sent';
    case MissedPickup = 'missed_pickup';
    case OutForDelivery = 'out_for_delivery';
    case Delivered = 'delivered';
    case DeliveryFailed = 'delivery_failed';
    case Rescheduled = 'rescheduled';
    case Refunded = 'refunded';
    case Shipped = 'shipped';
    case ArrivedWarm = 'arrived_warm';

    public function label(): string
    {
        return match ($this) {
            self::ReadyForPickup => 'Marked ready for pickup',
            self::PickedUp => 'Picked up',
            self::PickupReminderSent => 'Pickup reminder sent',
            self::MissedPickup => 'Missed pickup — written off',
            self::OutForDelivery => 'Out for delivery',
            self::Delivered => 'Delivered',
            self::DeliveryFailed => 'Delivery attempt failed',
            self::Rescheduled => 'Rescheduled',
            self::Refunded => 'Refund issued',
            self::Shipped => 'Shipped',
            self::ArrivedWarm => 'Reported arrived warm — cold-chain failure',
        };
    }
}
