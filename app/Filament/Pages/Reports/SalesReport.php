<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Enums\Permission;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;
use UnitEnum;

/**
 * Guideline ch. 6, Sprint 07: revenue by day and by product, for orders that reached a final weighed
 * total. Grouped by placement date, not settlement date — a reasonable v1 simplification; see
 * docs/sprint-07.md.
 */
class SalesReport extends Page
{
    protected string $view = 'filament.pages.reports.sales-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?string $title = 'Sales';

    /**
     * Each row: day (string), revenue_cents (int), orders (int).
     *
     * @var Collection<int, stdClass>
     */
    public Collection $byDay;

    /**
     * Each row: product_name (string), revenue_cents (int), qty (int).
     *
     * @var Collection<int, stdClass>
     */
    public Collection $byProduct;

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ViewReports->value) ?? false;
    }

    public function mount(): void
    {
        $this->byDay = DB::table('orders')
            ->whereNotNull('final_cents')
            ->selectRaw('DATE(created_at) as day, SUM(final_cents + delivery_fee_cents + tax_cents - discount_cents) as revenue_cents, COUNT(*) as orders')
            ->groupBy('day')
            ->orderByDesc('day')
            ->limit(30)
            ->get();

        $this->byProduct = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->whereNotNull('orders.final_cents')
            ->selectRaw('order_items.product_name, SUM(order_items.final_cents) as revenue_cents, SUM(order_items.quantity) as qty')
            ->groupBy('order_items.product_name')
            ->orderByDesc('revenue_cents')
            ->limit(20)
            ->get();
    }
}
