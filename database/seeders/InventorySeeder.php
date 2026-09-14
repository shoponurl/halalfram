<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Inventory\ReceiveStock;
use App\Enums\Role;
use App\Enums\StorageLocation;
use App\Models\Animal;
use App\Models\Lot;
use App\Models\Product;
use App\Models\User;
use App\Support\Weight;
use Illuminate\Database\Seeder;

/**
 * A representative example of one animal and one lot in stock (guideline ch. 6, Sprint 03) — not the
 * owner's real inventory. Local/staging only, so demand-test/dev environments have something to
 * reserve against; real receiving happens via /admin/animals and /admin/lots.
 */
class InventorySeeder extends Seeder
{
    public function run(): void
    {
        $product = Product::query()->where('slug', 'goat-whole')->first();
        if ($product === null) {
            return;
        }

        $butcher = User::query()->where('email', Role::Butcher->value.'@halalbrothers.test')->first();
        if ($butcher === null) {
            return;
        }

        $animal = Animal::query()->firstOrNew(['tag_id' => 'DEMO-G001']);
        if (! $animal->exists) {
            $animal->fill([
                'species' => $product->category?->species,
                'slaughter_date' => now()->subDay(),
                'live_weight_lb' => Weight::pounds('60.000'),
                'dressed_weight_lb' => Weight::pounds('30.000'),
                'notes' => 'Demo animal for staging — not a real traceability record.',
                'recorded_by' => $butcher->id,
            ]);
            $animal->save();
        }

        if (Lot::query()->where('product_id', $product->id)->exists()) {
            return;
        }

        app(ReceiveStock::class)->handle(
            product: $product,
            animal: $animal,
            storageLocation: StorageLocation::Chiller,
            packDate: now(),
            useByDate: now()->addDays(7),
            weight: Weight::pounds('30.000'),
            receivedBy: $butcher,
        );
    }
}
