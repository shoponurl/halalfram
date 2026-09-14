<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Actions\Action;
use App\Enums\LotStatus;
use App\Enums\StockMovementType;
use App\Models\Lot;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Weight;
use Illuminate\Validation\ValidationException;

/**
 * Logs spoilage or loss that isn't tied to any order — a lot going bad in the freezer, a scale error
 * found during a count (guideline ch. 6, Sprint 03 reconciliation: live weight = packed + wastage +
 * unexplained). A manual, staff-attributed movement, never silent.
 */
final class RecordWastage extends Action
{
    public function handle(Lot $lot, Weight $weight, User $recordedBy, string $note): StockMovement
    {
        return $this->transaction(function () use ($lot, $weight, $recordedBy, $note): StockMovement {
            /** @var Lot $locked */
            $locked = Lot::query()->whereKey($lot->id)->lockForUpdate()->firstOrFail();

            if (! $weight->isPositive()) {
                throw ValidationException::withMessages(['weight' => 'Wastage must be greater than zero.']);
            }
            if ($weight->compareTo($locked->on_hand_weight_lb) > 0) {
                throw ValidationException::withMessages(['weight' => 'Wastage can’t be more than what’s on hand.']);
            }

            $locked->on_hand_weight_lb = $locked->on_hand_weight_lb->minus($weight);
            if ($locked->on_hand_weight_lb->compareTo(Weight::zero()) <= 0) {
                $locked->status = LotStatus::Depleted;
            }
            $locked->save();
            $lot->setRawAttributes($locked->getAttributes(), true);

            $movement = new StockMovement;
            $movement->fill(['type' => StockMovementType::Wastage, 'weight_lb' => $weight, 'note' => $note]);
            $movement->lot_id = $locked->id;
            $movement->recorded_by = $recordedBy->id;
            $movement->save();

            return $movement;
        });
    }
}
