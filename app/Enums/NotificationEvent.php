<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Every lifecycle moment the guideline's Sprint 06 "notification system" can tell a customer about.
 * Each one has a default App\Models\NotificationTemplate per channel, seeded by NotificationTemplateSeeder
 * and editable in Filament without a deploy.
 */
enum NotificationEvent: string
{
    case OrderPlaced = 'order.placed';
    case PaymentAuthorized = 'order.payment_authorized';
    case ReadyForPickup = 'order.ready_for_pickup';
    case OutForDelivery = 'order.out_for_delivery';
    case Delivered = 'order.delivered';
    case DeliveryFailed = 'order.delivery_failed';
    case PickupReminder = 'order.pickup_reminder';
    case BalanceDue = 'order.balance_due';
    case Refunded = 'order.refunded';
    case Completed = 'order.completed';

    public function label(): string
    {
        return match ($this) {
            self::OrderPlaced => 'Order placed',
            self::PaymentAuthorized => 'Payment authorized',
            self::ReadyForPickup => 'Ready for pickup',
            self::OutForDelivery => 'Out for delivery',
            self::Delivered => 'Delivered',
            self::DeliveryFailed => 'Delivery attempt failed',
            self::PickupReminder => 'Pickup reminder',
            self::BalanceDue => 'Balance due',
            self::Refunded => 'Refunded',
            self::Completed => 'Order completed',
        };
    }
}
