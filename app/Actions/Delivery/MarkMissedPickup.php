<?php

declare(strict_types=1);

namespace App\Actions\Delivery;

use App\Actions\Action;
use App\Enums\DeliveryEventType;
use App\Enums\FulfilmentStatus;
use App\Enums\NotificationEvent;
use App\Enums\OrderStatus;
use App\Jobs\DispatchOrderNotification;
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
        /** @var array{refund_cents: int, cash_written_off: bool} $result */
        $result = $this->transaction(function () use ($order): array {
            /** @var Order $locked */
            $locked = Order::query()->whereKey($order->getKey())->lockForUpdate()->firstOrFail();
            if ($locked->fulfilment_status !== FulfilmentStatus::ReadyForPickup) {
                return ['refund_cents' => 0, 'cash_written_off' => false];
            }

            $locked->fulfilment_status = FulfilmentStatus::MissedPickup;
            $locked->save();

            $event = new DeliveryEvent;
            $event->fill(['type' => DeliveryEventType::MissedPickup]);
            $locked->deliveryEvents()->save($event);

            // Cash on pickup (guideline S06): nothing was ever collected, so there's nothing to refund —
            // only to write off. Closed out here, in the same transaction, since there's no gateway call
            // to retry on a queue.
            if ($locked->payment_method === 'cash') {
                $locked->status = OrderStatus::Cancelled;
                $locked->written_off_cents = (int) $locked->final_cents;
                $locked->fulfilment_status = FulfilmentStatus::Refunded;
                $locked->save();

                $writeOff = new DeliveryEvent;
                $writeOff->fill(['type' => DeliveryEventType::Refunded, 'note' => 'Cash order, never collected — written off, nothing to refund.']);
                $locked->deliveryEvents()->save($writeOff);

                return ['refund_cents' => 0, 'cash_written_off' => true];
            }

            $refundPct = (int) config('catchweight.pickup_writeoff_refund_pct');

            return ['refund_cents' => (int) round(((int) $locked->final_cents) * $refundPct / 100), 'cash_written_off' => false];
        });

        if ($result['refund_cents'] > 0) {
            RefundFulfilment::dispatch($order->id, $result['refund_cents'], 'missed_pickup');
        } elseif ($result['cash_written_off']) {
            DispatchOrderNotification::dispatch($order->id, NotificationEvent::Refunded->value);
        }
    }
}
