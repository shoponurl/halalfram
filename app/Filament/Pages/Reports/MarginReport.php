<?php

declare(strict_types=1);

namespace App\Filament\Pages\Reports;

use App\Enums\Permission;
use App\Models\Animal;
use App\Models\OrderItem;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use UnitEnum;

/**
 * Guideline ch. 6/7, Sprint 07: revenue vs. cost per animal. Owner decision (S07, recorded
 * 2026-09-15): own-farm animals have no purchase invoice, so their cost is a manual estimate — every
 * row below is flagged "(estimated)" when it is one, never blended in as if it were real, invoiced cost.
 */
class MarginReport extends Page
{
    protected string $view = 'filament.pages.reports.margin-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static string|UnitEnum|null $navigationGroup = 'Reports';

    protected static ?string $title = 'Margin';

    /** @var Collection<int, array{animal: Animal, revenue_cents: int, cost_cents: int, margin_cents: int}> */
    public Collection $rows;

    /** @var Collection<int, Animal> */
    public Collection $uncosted;

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ViewReports->value) ?? false;
    }

    public function mount(): void
    {
        $animals = Animal::query()->with('lots')->orderByDesc('slaughter_date')->get();

        $costed = $animals->filter(fn (Animal $animal) => $animal->cost_cents !== null);
        $this->uncosted = $animals->filter(fn (Animal $animal) => $animal->cost_cents === null)->values();

        $this->rows = $costed->map(function (Animal $animal): array {
            $lotIds = $animal->lots->pluck('id');
            $revenueCents = (int) OrderItem::query()->whereIn('lot_id', $lotIds)->whereNotNull('final_cents')->sum('final_cents');
            $costCents = (int) $animal->cost_cents;

            return [
                'animal' => $animal,
                'revenue_cents' => $revenueCents,
                'cost_cents' => $costCents,
                'margin_cents' => $revenueCents - $costCents,
            ];
        })->values();
    }
}
