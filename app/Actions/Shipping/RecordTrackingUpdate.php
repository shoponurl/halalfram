<?php

declare(strict_types=1);

namespace App\Actions\Shipping;

use App\Actions\Action;
use App\Enums\DeliveryEventType;
use App\Enums\FulfilmentStatus;
use App\Enums\NotificationEvent;
use App\Jobs\DispatchOrderNotification;
use App\Models\DeliveryEvent;
use App\Models\Order;
use App\Shipping\Data\TrackingUpdate;

/**
 * Applies an EasyPost tracking webhook to the matching order. A carrier-reported "failure" (lost,
 * misrouted) is recorded but doesn't itself trigger a refund — a cold-chain failure (arrived warm) is
 * always customer-reported, via App\Actions\Shipping\MarkArrivedWarm.
 */
final class RecordTrackingUpdate extends Action
{
    public function handle(TrackingUpdate $update): void
    {
        $orderId = $this->transaction(function () use ($update): ?int {
            $order = Order::query()->where('easypost_shipment_id', $update->shipmentId)->lockForUpdate()->first();
            if ($order === null || $order->fulfilment_status !== FulfilmentStatus::Shipped) {
                return null;
            }

            if ($update->status === 'delivered') {
                $order->fulfilment_status = FulfilmentStatus::Delivered;
                $order->save();
                $event = new DeliveryEvent;
                $event->fill(['type' => DeliveryEventType::Delivered, 'note' => 'Delivered per carrier tracking']);
                $order->deliveryEvents()->save($event);

                return $order->id;
            }

            if ($update->status === 'failure') {
                $event = new DeliveryEvent;
                $event->fill(['type' => DeliveryEventType::DeliveryFailed, 'note' => 'Carrier reported a delivery exception']);
                $order->deliveryEvents()->save($event);
            }

            return null;
        });

        if ($orderId !== null) {
            DispatchOrderNotification::dispatch($orderId, NotificationEvent::Delivered->value);
        }
    }
}
