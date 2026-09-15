<?php

declare(strict_types=1);

namespace App\Actions\Shipping;

use App\Actions\Action;
use App\Enums\DeliveryEventType;
use App\Enums\FulfilmentStatus;
use App\Enums\NotificationEvent;
use App\Jobs\RefundFulfilment;
use App\Models\DeliveryEvent;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Owner decision (guideline ch. 7, S08): a cold-chain failure (arrived warm) is always a full refund —
 * never a resend or a partial credit. Reported by the customer, recorded by staff.
 */
final class MarkArrivedWarm extends Action
{
    public function handle(Order $order, string $note, User $by): Order
    {
        $order = $this->transaction(function () use ($order, $note, $by): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->fulfilment !== 'shipping' || $locked->fulfilment_status !== FulfilmentStatus::Shipped) {
                throw ValidationException::withMessages(['fulfilment' => 'This order isn\'t a shipped order awaiting delivery.']);
            }

            $locked->fulfilment_status = FulfilmentStatus::ArrivedWarm;
            $locked->save();

            $event = new DeliveryEvent;
            $event->fill(['type' => DeliveryEventType::ArrivedWarm, 'note' => $note]);
            $event->recorded_by = $by->id;
            $locked->deliveryEvents()->save($event);

            $order->setRawAttributes($locked->getAttributes(), true);

            return $order;
        });

        RefundFulfilment::dispatch($order->id, (int) $order->final_cents, 'arrived_warm', NotificationEvent::ArrivedWarm->value);

        return $order;
    }
}
