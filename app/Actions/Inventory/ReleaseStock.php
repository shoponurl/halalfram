<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Actions\Action;
use App\Enums\StockMovementType;
use App\Models\Lot;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\StockMovement;

/**
 * Releases every reservation on an order that will never be fulfilled — a failed card hold or an
 * expired authorization (guideline ch. 6, Sprint 03). Idempotent: skips any item already released or
 * consumed, so it's safe to call from more than one failure path.
 */
final class ReleaseStock extends Action
{
    public function handle(Order $order): void
    {
        $this->transaction(function () use ($order): void {
            $items = $order->items()->whereNotNull('lot_id')->get();

            foreach ($items as $item) {
                $this->releaseOne($item);
            }
        });
    }

    private function releaseOne(OrderItem $item): void
    {
        $alreadySettled = StockMovement::query()->where('order_item_id', $item->id)
            ->whereIn('type', [StockMovementType::Released, StockMovementType::Consumed])
            ->exists();
        if ($alreadySettled || $item->reserved_raw_weight_lb === null) {
            return;
        }

        /** @var Lot $lot */
        $lot = Lot::query()->whereKey($item->lot_id)->lockForUpdate()->firstOrFail();
        $lot->reserved_weight_lb = $lot->reserved_weight_lb->minus($item->reserved_raw_weight_lb);
        $lot->save();

        $movement = new StockMovement;
        $movement->fill(['type' => StockMovementType::Released, 'weight_lb' => $item->reserved_raw_weight_lb]);
        $movement->lot_id = $lot->id;
        $movement->order_item_id = $item->id;
        $movement->save();
    }
}
