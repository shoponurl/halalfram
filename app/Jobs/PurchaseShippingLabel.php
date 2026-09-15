<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Shipping\ComputeShipDate;
use App\Enums\DeliveryEventType;
use App\Enums\FulfilmentStatus;
use App\Enums\NotificationEvent;
use App\Models\DeliveryEvent;
use App\Models\Order;
use App\Shipping\ShippingGateway;
use App\Support\Weight;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Buys the overnight label once an order is finalized (guideline ch. 6, S08). Guideline DoD: "the
 * system itself blocks shipping on the wrong day" — if today isn't a valid ship date
 * (App\Actions\Shipping\ComputeShipDate), this redispatches itself for the next valid one instead of
 * ever calling the carrier. Idempotent: skipped once the order already has a tracking number.
 */
final class PurchaseShippingLabel implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [300, 900, 3600];

    public function __construct(public readonly int $orderId) {}

    public function handle(ShippingGateway $gateway, ComputeShipDate $computeShipDate): void
    {
        $shipped = Cache::lock("ship-order:{$this->orderId}", 60)->block(30, function () use ($gateway, $computeShipDate): bool {
            $order = Order::query()->with('packingRule', 'items')->findOrFail($this->orderId);
            if ($order->fulfilment !== 'shipping' || $order->tracking_number !== null) {
                return false;
            }

            $validDate = $computeShipDate->handle();
            if (! $validDate->isToday()) {
                self::dispatch($this->orderId)->delay($validDate->startOfDay());

                return false;
            }

            $packingRule = $order->packingRule;
            if ($packingRule === null) {
                Log::critical('Shipping order has no packing rule — cannot buy a label', ['order_id' => $order->id]);

                return false;
            }

            $totalWeight = Weight::zero();
            foreach ($order->items as $item) {
                $totalWeight = $totalWeight->plus($item->actual_weight_lb ?? $item->estimated_weight_lb);
            }

            $label = $gateway->buyOvernightLabel(
                to: [
                    'name' => $order->customer_name,
                    'address1' => (string) $order->delivery_address_line1,
                    'address2' => $order->delivery_address_line2,
                    'city' => (string) $order->delivery_city,
                    'state' => (string) $order->delivery_state,
                    'zip' => (string) $order->delivery_zip,
                ],
                parcel: [
                    'length_in' => (string) $packingRule->box_length_in,
                    'width_in' => (string) $packingRule->box_width_in,
                    'height_in' => (string) $packingRule->box_height_in,
                    'weight_lb' => $packingRule->totalWeightFor($totalWeight)->toDecimal(),
                ],
            );

            $order->easypost_shipment_id = $label->shipmentId;
            $order->shipping_carrier = $label->carrier;
            $order->shipping_service = $label->service;
            $order->shipping_actual_rate_cents = $label->rateCents;
            $order->tracking_number = $label->trackingNumber;
            $order->tracking_url = $label->trackingUrl;
            $order->shipping_label_url = $label->labelUrl;
            $order->shipped_at = now();
            $order->fulfilment_status = FulfilmentStatus::Shipped;
            $order->save();

            $event = new DeliveryEvent;
            $event->fill(['type' => DeliveryEventType::Shipped, 'note' => "{$label->carrier} {$label->service}, tracking {$label->trackingNumber}"]);
            $order->deliveryEvents()->save($event);

            return true;
        });

        if ($shipped) {
            DispatchOrderNotification::dispatch($this->orderId, NotificationEvent::Shipped->value);
        }
    }

    public function failed(Throwable $e): void
    {
        Log::critical('Purchasing a shipping label failed after retries', ['order_id' => $this->orderId, 'error' => $e->getMessage()]);
    }
}
