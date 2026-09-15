<?php

declare(strict_types=1);

namespace App\Actions\Delivery;

use App\Actions\Action;
use App\Enums\DeliveryEventType;
use App\Enums\NotificationEvent;
use App\Jobs\DispatchOrderNotification;
use App\Models\DeliveryEvent;
use App\Models\Order;

/** Owner decision (guideline ch. 7, S05): one reminder before a missed pickup is written off. */
final class SendPickupReminder extends Action
{
    public function handle(Order $order): void
    {
        $sent = $this->transaction(function () use ($order): bool {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->pickup_reminder_sent_at !== null) {
                return false;
            }

            $locked->pickup_reminder_sent_at = now();
            $locked->save();

            $event = new DeliveryEvent;
            $event->fill(['type' => DeliveryEventType::PickupReminderSent]);
            $locked->deliveryEvents()->save($event);

            return true;
        });

        if ($sent) {
            DispatchOrderNotification::dispatch($order->id, NotificationEvent::PickupReminder->value);
        }
    }
}
