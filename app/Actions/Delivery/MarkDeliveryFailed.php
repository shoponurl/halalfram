<?php

declare(strict_types=1);

namespace App\Actions\Delivery;

use App\Actions\Action;
use App\Enums\DeliveryEventType;
use App\Enums\FulfilmentStatus;
use App\Jobs\RefundFulfilment;
use App\Models\DeliveryEvent;
use App\Models\Order;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Owner decision (guideline ch. 7, S05): no one home → return to store → one free re-attempt → then
 * refund the actual total minus the delivery fee (the fee covers the driver's real trip either way).
 */
final class MarkDeliveryFailed extends Action
{
    public function handle(Order $order, User $driver, string $note): Order
    {
        $result = $this->transaction(function () use ($order, $driver, $note): array {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->fulfilment_status !== FulfilmentStatus::OutForDelivery) {
                throw ValidationException::withMessages(['fulfilment' => 'This order isn’t out for delivery.']);
            }

            $locked->delivery_attempts++;
            $maxAttempts = (int) config('catchweight.delivery_max_attempts');
            $final = $locked->delivery_attempts >= $maxAttempts;
            $locked->fulfilment_status = $final ? FulfilmentStatus::Returned : FulfilmentStatus::DeliveryFailed;
            $locked->save();

            $event = new DeliveryEvent;
            $event->fill(['type' => DeliveryEventType::DeliveryFailed, 'note' => $note]);
            $event->recorded_by = $driver->id;
            $locked->deliveryEvents()->save($event);

            $order->setRawAttributes($locked->getAttributes(), true);

            $refundCents = $final ? max(0, (int) $locked->final_cents - $locked->delivery_fee_cents) : 0;

            return [$order, $refundCents];
        });

        [$updated, $refundCents] = $result;

        if ($refundCents > 0) {
            RefundFulfilment::dispatch($updated->id, $refundCents, 'delivery_failed_final');
        }

        return $updated;
    }
}
