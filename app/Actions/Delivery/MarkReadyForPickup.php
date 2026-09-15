<?php

declare(strict_types=1);

namespace App\Actions\Delivery;

use App\Actions\Action;
use App\Enums\DeliveryEventType;
use App\Enums\FulfilmentStatus;
use App\Enums\OrderStatus;
use App\Models\DeliveryEvent;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Guideline ch. 6, Sprint 05: store pickup flow + a "ready" notification (tracking page, for now). */
final class MarkReadyForPickup extends Action
{
    public function handle(Order $order, User $by): Order
    {
        return $this->transaction(function () use ($order, $by): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->fulfilment !== 'pickup') {
                throw ValidationException::withMessages(['fulfilment' => 'This order is for delivery, not pickup.']);
            }
            if (! in_array($locked->status, [OrderStatus::Completed, OrderStatus::AwaitingBalance], true)) {
                throw ValidationException::withMessages(['fulfilment' => 'The order isn’t paid yet.']);
            }
            if ($locked->fulfilment_status !== FulfilmentStatus::AwaitingFulfilment) {
                throw ValidationException::withMessages(['fulfilment' => "Already {$locked->fulfilment_status->label()}."]);
            }

            $locked->fulfilment_status = FulfilmentStatus::ReadyForPickup;
            $locked->ready_notified_at = now();
            $locked->save();

            $event = new DeliveryEvent;
            $event->fill(['type' => DeliveryEventType::ReadyForPickup]);
            $event->recorded_by = $by->id;
            $locked->deliveryEvents()->save($event);

            $order->setRawAttributes($locked->getAttributes(), true);

            return $order;
        });
    }
}
