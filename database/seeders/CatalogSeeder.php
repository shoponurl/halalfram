<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\PortionType;
use App\Models\Category;
use App\Models\Product;
use App\Support\Weight;
use Illuminate\Database\Seeder;

/**
 * A representative starter catalog across species (guideline ch. 6, Sprint 02). Idempotent (matched by
 * slug). This is example data, not the owner's real catalog — real products and photos go in via
 * /admin/products in staging.
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $chicken = Category::query()->where('slug', 'chicken')->first();
        $goat = Category::query()->where('slug', 'goat')->first();
        $beef = Category::query()->where('slug', 'beef')->first();
        $deli = Category::query()->where('slug', 'processed-deli')->first();

        $this->upsert('whole-chicken-air-chilled', [
            'category_id' => $chicken?->id,
            'name' => 'Whole Chicken, Air-chilled',
            'description' => 'Hand-slaughtered zabiha, air-chilled with no added water. Priced by weight — each bird is about 3.5 lb.',
            'portion_type' => PortionType::Whole,
            'price_per_lb_cents' => 349,
            'estimated_weight_lb' => Weight::pounds('3.500'),
            'is_active' => true,
        ]);

        $this->upsert('goat-whole', [
            'category_id' => $goat?->id,
            'name' => 'Goat, Whole',
            'description' => 'Hand-slaughtered zabiha goat, dressed weight. Choose your cut, offal and packing preference.',
            'portion_type' => PortionType::Whole,
            'yield_pct' => '50.00',
            'price_per_lb_cents' => 899,
            'estimated_weight_lb' => Weight::pounds('25.000'),
            'is_active' => true,
        ]);

        $this->upsert('beef-quarter', [
            'category_id' => $beef?->id,
            'name' => 'Beef, Quarter',
            'description' => 'Zabiha beef, quarter share of the dressed animal. Choose your cut and packing preference.',
            'portion_type' => PortionType::Quarter,
            'yield_pct' => '24.00',
            'price_per_lb_cents' => 699,
            'estimated_weight_lb' => Weight::pounds('110.000'),
            'is_active' => true,
        ]);

        $this->upsert('beef-sausage-mild', [
            'category_id' => $deli?->id,
            'name' => 'Beef Sausage, Mild',
            'description' => 'In-house processed, no cut/offal options — sold ready to cook.',
            'portion_type' => PortionType::Pack,
            'price_per_lb_cents' => 799,
            'estimated_weight_lb' => Weight::pounds('1.000'),
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
