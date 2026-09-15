<?php

declare(strict_types=1);

namespace App\Actions\Delivery;

use App\Actions\Action;
use App\Enums\DeliveryEventType;
use App\Enums\FulfilmentStatus;
use App\Jobs\RefundFulfilment;
use App\Models\DeliveryEvent;
use App\Models\Order;

/**
 * Owner decision (guideline ch. 7, S05): still uncollected by the write-off deadline → written off,
 * customer refunded their share (config('catchweight.pickup_writeoff_refund_pct')) — the rest covers
 * the product, which can no longer be safely resold.
 */
final class MarkMissedPickup extends Action
{
    public function handle(Order $order): void
    {
        $result = $this->transaction(function () use ($order): int {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->fulfilment_status !== FulfilmentStatus::ReadyForPickup) {
                return 0;
            }

            $locked->fulfilment_status = FulfilmentStatus::MissedPickup;
            $locked->save();

            $event = new DeliveryEvent;
            $event->fill(['type' => DeliveryEventType::MissedPickup]);
            $locked->deliveryEvents()->save($event);

            $refundPct = (int) config('catchweight.pickup_writeoff_refund_pct');

            return (int) round(((int) $locked->final_cents) * $refundPct / 100);
        });

        if ($result > 0) {
            RefundFulfilment::dispatch($order->id, $result, 'missed_pickup');
        }
    }
}
