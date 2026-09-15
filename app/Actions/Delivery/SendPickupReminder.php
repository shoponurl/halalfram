<?php

declare(strict_types=1);

namespace App\Actions\Delivery;

use App\Actions\Action;
use App\Enums\DeliveryEventType;
use App\Models\DeliveryEvent;
use App\Models\Order;

/**
 * Owner decision (guideline ch. 7, S05): one reminder before a missed pickup is written off. No
 * SMS/email channel is wired up until Sprint 06 — for now this just marks the tracking page state;
 * a human calls or texts the customer using the info already on the order.
 */
final class SendPickupReminder extends Action
{
    public function handle(Order $order): void
    {
        $this->transaction(function () use ($order): void {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->pickup_reminder_sent_at !== null) {
                return;
            }

            $locked->pickup_reminder_sent_at = now();
            $locked->save();

            $event = new DeliveryEvent;
            $event->fill(['type' => DeliveryEventType::PickupReminderSent]);
            $locked->deliveryEvents()->save($event);
        });
    }
}
