<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Enums\LotStatus;
use App\Enums\Permission;
use App\Models\Lot;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/** Guideline ch. 6, Sprint 07: active lots, oldest/soonest-to-expire first — a FEFO worklist. */
class StockAgingReport extends Page
{
    protected string $view = 'filament.pages.reports.stock-aging-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?string $title = 'Stock aging';

    /** @var Collection<int, Lot> */
    public Collection $lots;

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ViewReports->value) ?? false;
    }

    public function mount(): void
    {
        $this->lots = Lot::query()
            ->with(['product', 'animal'])
            ->where('status', LotStatus::Active)
            ->orderBy('use_by_date')
            ->get();
    }
}
