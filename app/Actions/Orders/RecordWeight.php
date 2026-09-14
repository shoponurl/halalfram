<?php

declare(strict_types=1);

namespace App\Actions\Orders;

use App\Actions\Action;
use App\Enums\WeightSource;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\User;
use App\Models\WeightEvent;
use App\Support\CatchWeightPricing;
use App\Support\Weight;
use Illuminate\Validation\ValidationException;

/**
 * Appends a weighing for one line (rule 04) and refreshes the line's cached actual weight and price.
 * Authorization (weights.record) is checked by the caller's policy before this runs.
 */
final class RecordWeight extends Action
{
    /** Guards against a typo like 35 instead of 3.5 reaching the card. */
    private const MAX_FACTOR_OF_ESTIMATE = 5;

    public function handle(OrderItem $item, Weight $weight, WeightSource $source, User $recordedBy, ?string $note = null, ?string $scaleId = null): WeightEvent
    {
        return $this->transaction(function () use ($item, $weight, $source, $recordedBy, $note, $scaleId): WeightEvent {
            /** @var Order $order */
            $order = Order::query()->whereKey($item->order_id)->lockForUpdate()->firstOrFail();

            // Rule 05: once settlement starts, weights are frozen — corrections go through a credit note
            if ($order->weights_locked_at !== null || ! $order->status->acceptsWeights()) {
                throw ValidationException::withMessages(['weight' => "Order {$order->number} is locked for weighing ({$order->status->label()})."]);
            }
            if (! $weight->isPositive()) {
                throw ValidationException::withMessages(['weight' => 'Weight must be greater than zero.']);
            }
            if ($weight->compareTo($item->estimated_weight_lb->times(self::MAX_FACTOR_OF_ESTIMATE)) > 0) {
                throw ValidationException::withMessages(['weight' => 'That weight is more than 5× the estimate — please check the entry.']);
            }

            $event = new WeightEvent;
            $event->fill(['weight_lb' => $weight, 'source' => $source, 'note' => $note, 'scale_id' => $scaleId]);
            $event->order_item_id = $item->id;
            $event->recorded_by = $recordedBy->id;
            $event->save();

            $item->actual_weight_lb = $weight;
            $item->final_cents = CatchWeightPricing::lineCents($item->price_per_lb_cents, $weight);
            $item->save();

            return $event;
        });
    }
}
