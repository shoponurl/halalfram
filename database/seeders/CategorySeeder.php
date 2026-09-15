<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\CutStyle;
use App\Enums\Species;
use App\Models\Category;
use App\Models\CutOption;
use App\Models\OffalOption;
use App\Models\PackingOption;
use Illuminate\Database\Seeder;

/**
 * Sprint 02 catalog structure (guideline ch. 6, M03): a starter category/cut/offal/packing set per
 * species, idempotent by slug/name. This is a representative example, not the owner's real catalog —
 * the real cuts, prices, yield % and photos go in via /admin/categories, /admin/cut-options etc. in
 * staging. Raw yield % (Sprint 03) and butcher-minutes (Sprint 04) are illustrative starting points.
 */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $chicken = $this->category('chicken', 'Chicken', Species::Chicken, true);
        $goat = $this->category('goat', 'Goat', Species::Goat, true);
        $beef = $this->category('beef', 'Beef', Species::Beef, true);
        $this->category('processed-deli', 'Processed & deli', Species::Processed, false);

        $this->cutOption($chicken, 'Whole (uncut)', CutStyle::Whole, 0, 0, null, 5);
        $this->cutOption($chicken, 'Cut into 8 pieces', CutStyle::CurryCut, 100, 0, null, 8);
        $this->cutOption($chicken, 'Boneless breast', CutStyle::Boneless, 200, 1, '65.00', 12);
        $this->offalOption($chicken, 'Keep giblets', 0);
        $this->offalOption($chicken, 'Discard giblets', 0);

        $this->cutOption($goat, 'Curry cut', CutStyle::CurryCut, 0, 0, null, 15);
        $this->cutOption($goat, 'Chops', CutStyle::Chops, 300, 1, '85.00', 20);
        $this->cutOption($goat, 'Boneless', CutStyle::Boneless, 400, 2, '65.00', 30);
        $this->offalOption($goat, 'Keep all offal', 0);
        $this->offalOption($goat, 'Discard all offal', 0);
        $this->offalOption($goat, 'Separate pack (liver, tripe, feet)', 300);

        $this->cutOption($beef, 'Curry cut', CutStyle::CurryCut, 0, 0, null, 15);
        $this->cutOption($beef, 'Steaks', CutStyle::Steak, 300, 1, '80.00', 20);
        $this->cutOption($beef, 'Mince', CutStyle::Mince, 150, 0, '90.00', 10);
        $this->offalOption($beef, 'Keep all offal', 0);
        $this->offalOption($beef, 'Discard all offal', 0);

        $this->packingOption('Standard packaging', 0, 0);
        $this->packingOption('Vacuum pack', 150, 0);
        $this->packingOption('1 lb portions, vacuum packed', 250, 1);
    }

    private function category(string $slug, string $name, Species $species, bool $supportsCustomCuts): Category
    {
        $category = Category::query()->firstOrNew(['slug' => $slug]);
        $category->fill(['name' => $name, 'species' => $species, 'supports_custom_cuts' => $supportsCustomCuts]);
        $category->save();

        return $category;
    }

    private function cutOption(Category $category, string $name, CutStyle $style, int $extraPriceCents, int $extraLeadTimeDays, ?string $rawYieldPct, int $estimatedMinutes): void
    {
        $option = CutOption::query()->firstOrNew(['category_id' => $category->id, 'name' => $name]);
        $option->fill([
            'cut_style' => $style,
            'extra_price_cents' => $extraPriceCents,
            'extra_lead_time_days' => $extraLeadTimeDays,
            'raw_yield_pct' => $rawYieldPct,
            'estimated_minutes' => $estimatedMinutes,
            'is_active' => true,
        ]);
        $option->save();
    }

    private function offalOption(Category $category, string $name, int $extraPriceCents): void
    {
        $option = OffalOption::query()->firstOrNew(['category_id' => $category->id, 'name' => $name]);
        $option->fill(['extra_price_cents' => $extraPriceCents, 'is_active' => true]);
        $option->save();
    }

    private function packingOption(string $name, int $surchargeCents, int $extraLeadTimeDays): void
    {
        $option = PackingOption::query()->firstOrNew(['name' => $name]);
        $option->fill(['surcharge_cents' => $surchargeCents, 'extra_lead_time_days' => $extraLeadTimeDays, 'is_active' => true]);
        $option->save();
    }
}
