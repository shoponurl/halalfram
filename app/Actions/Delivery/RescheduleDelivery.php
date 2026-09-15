<?php

declare(strict_types=1);

namespace App\Actions\Delivery;

use App\Actions\Action;
use App\Enums\DeliveryEventType;
use App\Enums\FulfilmentStatus;
use App\Models\DeliveryEvent;
use App\Models\DeliverySlot;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/** Guideline ch. 6, Sprint 05: failed delivery & reschedule — a new slot after a failed attempt. */
final class RescheduleDelivery extends Action
{
    public function __construct(private readonly ReserveDeliverySlot $reserveSlot) {}

    public function handle(Order $order, DeliverySlot $newSlot, User $by): Order
    {
        return $this->transaction(function () use ($order, $newSlot, $by): Order {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->fulfilment_status !== FulfilmentStatus::DeliveryFailed) {
                throw ValidationException::withMessages(['fulfilment' => 'Only a failed delivery can be rescheduled.']);
            }

            $slot = $this->reserveSlot->handle($newSlot->id);

            $locked->delivery_slot_id = $slot->id;
            $locked->fulfilment_status = FulfilmentStatus::AwaitingFulfilment;
            $locked->save();

            $event = new DeliveryEvent;
            $event->fill(['type' => DeliveryEventType::Rescheduled, 'note' => $slot->label()]);
            $event->recorded_by = $by->id;
            $locked->deliveryEvents()->save($event);

            $order->setRawAttributes($locked->getAttributes(), true);

            return $order;
        });
    }
}
