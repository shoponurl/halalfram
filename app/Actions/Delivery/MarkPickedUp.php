<?php

declare(strict_types=1);

namespace App\Actions\Delivery;

use App\Actions\Action;
use App\Enums\DeliveryEventType;
use App\Enums\FulfilmentStatus;
use App\Models\DeliveryEvent;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

final class MarkPickedUp extends Action
{
    public function handle(Order $order, User $by): Order
    {
        return $this->transaction(function () use ($order, $by): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->fulfilment_status !== FulfilmentStatus::ReadyForPickup) {
                throw ValidationException::withMessages(['fulfilment' => 'This order isn’t ready for pickup.']);
            }

            $locked->fulfilment_status = FulfilmentStatus::PickedUp;
            $locked->save();

            $event = new DeliveryEvent;
            $event->fill(['type' => DeliveryEventType::PickedUp]);
            $event->recorded_by = $by->id;
            $locked->deliveryEvents()->save($event);

            $order->setRawAttributes($locked->getAttributes(), true);

            return $order;
        });
    }
}
