<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Actions\Action;
use App\Enums\StockMovementType;
use App\Models\CutOption;
use App\Models\Lot;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Validation\ValidationException;

/**
 * Reserves raw stock for a placed order line, FEFO (guideline ch. 6, Sprint 03). A product with no
 * lots at all is untracked — Sprint 01/02 checkout behaves exactly as before. Owner policy (S03):
 * stock is deducted by raw material including trim loss, and a reservation holds for the whole order
 * lifecycle (released only on failure/expiry, converted to a consumption at finalization).
 */
final class ReserveStock extends Action
{
    public function handle(OrderItem $item, Product $product, ?CutOption $cutOption): void
    {
        $this->transaction(function () use ($item, $product, $cutOption): void {
            if (! Lot::query()->where('product_id', $product->id)->exists()) {
                return;
            }

            $neededRaw = $cutOption?->rawWeightFor($item->estimated_weight_lb) ?? $item->estimated_weight_lb;

            $lot = Lot::query()->availableFor($product->id)->lockForUpdate()->get()
                ->first(fn (Lot $candidate) => $candidate->availableWeight()->compareTo($neededRaw) >= 0);

            if ($lot === null) {
                throw ValidationException::withMessages(['cart' => "{$product->name} doesn't have enough stock available right now."]);
            }

            $lot->reserved_weight_lb = $lot->reserved_weight_lb->plus($neededRaw);
            $lot->save();

            $movement = new StockMovement;
            $movement->fill(['type' => StockMovementType::Reserved, 'weight_lb' => $neededRaw]);
            $movement->lot_id = $lot->id;
            $movement->order_item_id = $item->id;
            $movement->save();

            $item->lot_id = $lot->id;
            $item->reserved_raw_weight_lb = $neededRaw;
            $item->save();
        });
    }
}
