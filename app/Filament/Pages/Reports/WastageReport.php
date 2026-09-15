<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Enums\Permission;
use App\Enums\StockMovementType;
use App\Models\Animal;
use App\Models\StockMovement;
use App\Support\Weight;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Guideline ch. 6, Sprint 07: spoilage/loss not tied to any order (App\Enums\StockMovementType::Wastage)
 * plus each animal's dressing loss (App\Models\Animal::dressingLoss, guideline S03 reconciliation).
 */
class WastageReport extends Page
{
    protected string $view = 'filament.pages.reports.wastage-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrash;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?string $title = 'Wastage';

    /** @var Collection<int, StockMovement> */
    public Collection $movements;

    /** @var Collection<int, Animal> */
    public Collection $animals;

    /** @var numeric-string */
    public string $totalWastageWeightLb;

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ViewReports->value) ?? false;
    }

    public function mount(): void
    {
        $this->movements = StockMovement::query()
            ->where('type', StockMovementType::Wastage)
            ->with('lot.product')
            ->orderByDesc('created_at')
            ->limit(200)
            ->get();

        $total = Weight::zero();
        foreach ($this->movements as $movement) {
            $total = $total->plus($movement->weight_lb);
        }
        $this->totalWastageWeightLb = $total->toDecimal();

        $this->animals = Animal::query()
            ->whereNotNull('live_weight_lb')
            ->whereNotNull('dressed_weight_lb')
            ->orderByDesc('slaughter_date')
            ->limit(100)
            ->get();
    }
}
