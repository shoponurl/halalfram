<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Product;
use App\Support\Weight;
use Illuminate\Database\Seeder;

/**
 * Sprint 01 catalog: one real catch-weight product, plus a $1 test pack for the staging end-to-end payment check.
 * Idempotent (matched by slug). Real photos and the full catalog come in Sprint 02.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $this->upsert('whole-chicken-air-chilled', [
            'name' => 'Whole Chicken, Air-chilled',
            'description' => 'Hand-slaughtered zabiha, air-chilled with no added water. Priced by weight — each bird is about 3.5 lb.',
            'price_per_lb_cents' => 349,
            'estimated_weight_lb' => Weight::pounds('3.500'),
            'is_active' => true,
        ]);

        if (app()->environment(['local', 'staging', 'testing'])) {
            $this->upsert('staging-test-pack', [
                'name' => 'Staging test pack ($1)',
                'description' => 'For end-to-end payment testing on staging only. Estimated $1.00 for 1 lb.',
                'price_per_lb_cents' => 100,
                'estimated_weight_lb' => Weight::pounds('1.000'),
                'is_active' => true,
            ]);
        }
    }

    /** @param array<string, mixed> $attributes */
    private function upsert(string $slug, array $attributes): void
    {
        $product = Product::query()->firstOrNew(['slug' => $slug]);
        $product->fill($attributes);
        $product->save();
    }
}
