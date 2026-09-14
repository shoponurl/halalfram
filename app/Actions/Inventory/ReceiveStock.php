<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Actions\Action;
use App\Enums\LotStatus;
use App\Enums\StockMovementType;
use App\Enums\StorageLocation;
use App\Models\Animal;
use App\Models\Lot;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use App\Support\Weight;
use Illuminate\Support\Carbon;

/**
 * Opens a new lot and records its starting weight as the first stock_movement (rule 04: every pack
 * gets a lot number at the moment it's created).
 */
final class ReceiveStock extends Action
{
    public function handle(
        Product $product,
        ?Animal $animal,
        StorageLocation $storageLocation,
        Carbon $packDate,
        Carbon $useByDate,
        Weight $weight,
        User $receivedBy,
    ): Lot {
        return $this->transaction(function () use ($product, $animal, $storageLocation, $packDate, $useByDate, $weight, $receivedBy): Lot {
            $lot = new Lot;
            $lot->product_id = $product->id;
            $lot->animal_id = $animal?->id;
            $lot->storage_location = $storageLocation;
            $lot->pack_date = $packDate;
            $lot->use_by_date = $useByDate;
            $lot->status = LotStatus::Active;
            $lot->on_hand_weight_lb = $weight;
            $lot->reserved_weight_lb = Weight::zero();
            $lot->received_by = $receivedBy->id;
            $lot->save();

            $lot->lot_number = 'LOT-'.str_pad((string) $lot->id, 6, '0', STR_PAD_LEFT);
            $lot->save();

            $movement = new StockMovement;
            $movement->fill(['type' => StockMovementType::Received, 'weight_lb' => $weight]);
            $movement->lot_id = $lot->id;
            $movement->recorded_by = $receivedBy->id;
            $movement->save();

            return $lot;
        });
    }
}
