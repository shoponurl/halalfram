<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Enums\Permission;
use App\Models\CutOption;
use App\Models\OrderItem;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Guideline ch. 6, Sprint 07: actual finished-vs-raw yield per product/cut, against the modeled
 * yield% used to size holds (App\Models\CutOption::rawWeightFor, App\Models\Product::$yield_pct).
 */
class YieldReport extends Page
{
    protected string $view = 'filament.pages.reports.yield-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?string $title = 'Yield';

    /** Each row: product (string), cut_option (string|null), count (int), actual_pct (numeric-string|null), modeled_pct (string|null).
     *
     * @var Collection<int, mixed>
     */
    public Collection $rows;

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ViewReports->value) ?? false;
    }

    public function mount(): void
    {
        $items = OrderItem::query()
            ->with('product')
            ->whereNotNull('consumed_raw_weight_lb')
            ->whereNotNull('actual_weight_lb')
            ->get();

        $cutOptions = CutOption::query()->whereKey($items->pluck('cut_option_id')->filter()->unique())->get()->keyBy('id');

        $this->rows = $items
            ->groupBy(fn (OrderItem $item) => $item->product_id.'-'.($item->cut_option_id ?? '0'))
            ->map(function (Collection $group) use ($cutOptions): array {
                /** @var OrderItem $first */
                $first = $group->first();
                $cutOption = $first->cut_option_id !== null ? $cutOptions->get($first->cut_option_id) : null;

                $sumActualPct = '0';
                $count = 0;
                foreach ($group as $item) {
                    if ($item->actual_weight_lb === null || $item->consumed_raw_weight_lb === null) {
                        continue;
                    }
                    $actualPct = bcadd('100', $item->actual_weight_lb->variancePercentFrom($item->consumed_raw_weight_lb), 4);
                    $sumActualPct = bcadd($sumActualPct, $actualPct, 4);
                    $count++;
                }
                $avgActualPct = $count > 0 ? bcdiv($sumActualPct, (string) $count, 2) : null;

                $modeledPct = $cutOption->raw_yield_pct ?? $first->product?->yield_pct;

                return [
                    'product' => $first->product_name,
                    'cut_option' => $first->cut_option_name,
                    'count' => $group->count(),
                    'actual_pct' => $avgActualPct,
                    'modeled_pct' => $modeledPct,
                ];
            })
            ->sortBy('product')
            ->values();
    }
}
