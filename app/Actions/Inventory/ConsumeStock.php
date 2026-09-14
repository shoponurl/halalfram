<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Actions\Action;
use App\Enums\LotStatus;
use App\Enums\StockMovementType;
use App\Models\CutOption;
use App\Models\Lot;
use App\Models\OrderItem;
use App\Models\StockMovement;
use App\Support\Weight;

/**
 * Converts a reservation into an actual consumption once the order's weights are locked
 * (guideline ch. 6, Sprint 03): releases the estimated raw weight that was reserved, then deducts
 * the actual raw weight (including trim loss) from on-hand. Idempotent — safe to call more than once
 * for the same item, since FinalizeOrder only ever locks weights once per order.
 */
final class ConsumeStock extends Action
{
    public function handle(OrderItem $item): void
    {
        $this->transaction(function () use ($item): void {
            if ($item->lot_id === null || $item->actual_weight_lb === null) {
                return;
            }
            $alreadyConsumed = StockMovement::query()->where('order_item_id', $item->id)->where('type', StockMovementType::Consumed)->exists();
            if ($alreadyConsumed) {
                return;
            }

            /** @var Lot $lot */
            $lot = Lot::query()->whereKey($item->lot_id)->lockForUpdate()->firstOrFail();
            $cutOption = $item->cut_option_id !== null ? CutOption::query()->find($item->cut_option_id) : null;
            $actualRaw = $cutOption instanceof CutOption ? $cutOption->rawWeightFor($item->actual_weight_lb) : $item->actual_weight_lb;

            if ($item->reserved_raw_weight_lb !== null) {
                $lot->reserved_weight_lb = $lot->reserved_weight_lb->minus($item->reserved_raw_weight_lb);
                $released = new StockMovement;
                $released->fill(['type' => StockMovementType::Released, 'weight_lb' => $item->reserved_raw_weight_lb]);
                $released->lot_id = $lot->id;
                $released->order_item_id = $item->id;
                $released->save();
            }

            $lot->on_hand_weight_lb = $lot->on_hand_weight_lb->minus($actualRaw);
            if ($lot->on_hand_weight_lb->compareTo(Weight::zero()) <= 0) {
                $lot->status = LotStatus::Depleted;
            }
            $lot->save();

            $consumed = new StockMovement;
            $consumed->fill(['type' => StockMovementType::Consumed, 'weight_lb' => $actualRaw]);
            $consumed->lot_id = $lot->id;
            $consumed->order_item_id = $item->id;
            $consumed->save();

            $item->consumed_raw_weight_lb = $actualRaw;
            $item->save();
        });
    }
}
