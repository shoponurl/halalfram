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

/**
 * Guideline ch. 6, Sprint 05: proof of delivery via a customer-facing OTP. SMS isn't wired up until
 * Sprint 06, so the code is shown in plain on the customer's own order tracking page — the driver
 * asks the customer for it at handoff and enters it in MarkDelivered.
 */
final class MarkOutForDelivery extends Action
{
    public function handle(Order $order, User $driver): Order
    {
        return $this->transaction(function () use ($order, $driver): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->fulfilment !== 'delivery') {
                throw ValidationException::withMessages(['fulfilment' => 'This order is for pickup, not delivery.']);
            }
            if (! in_array($locked->status, [OrderStatus::Completed, OrderStatus::AwaitingBalance], true)) {
                throw ValidationException::withMessages(['fulfilment' => 'The order isn’t paid yet.']);
            }
            if (! in_array($locked->fulfilment_status, [FulfilmentStatus::AwaitingFulfilment, FulfilmentStatus::DeliveryFailed], true)) {
                throw ValidationException::withMessages(['fulfilment' => "Can’t dispatch from {$locked->fulfilment_status->label()}."]);
            }

            $locked->fulfilment_status = FulfilmentStatus::OutForDelivery;
            $locked->driver_id = $driver->id;
            $locked->delivery_otp = (string) random_int(100000, 999999);
            $locked->save();

            $event = new DeliveryEvent;
            $event->fill(['type' => DeliveryEventType::OutForDelivery]);
            $event->recorded_by = $driver->id;
            $locked->deliveryEvents()->save($event);

            $order->setRawAttributes($locked->getAttributes(), true);

            return $order;
        });
    }
}
