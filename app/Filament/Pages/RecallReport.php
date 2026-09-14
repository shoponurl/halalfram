<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Models\Lot;
use App\Models\Order;
use App\Models\OrderItem;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/**
 * Guideline ch. 6, Sprint 03 DoD: given a lot number, find every order it touched in seconds; given
 * an order, trace back to the lot and animal. One search box, both directions.
 */
class RecallReport extends Page
{
    protected string $view = 'filament.pages.recall-report';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMagnifyingGlass;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $title = 'Recall report';

    public string $query = '';

    public ?Lot $lot = null;

    public ?Order $order = null;

    /** @var Collection<int, OrderItem> */
    public Collection $affectedItems;

    public bool $searched = false;

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::ManageInventory->value) ?? false;
    }

    public function mount(): void
    {
        $this->affectedItems = new Collection;
    }

    public function search(): void
    {
        $term = trim($this->query);
        $this->searched = true;
        $this->lot = null;
        $this->order = null;
        $this->affectedItems = new Collection;

        if ($term === '') {
            return;
        }

        $this->lot = Lot::query()->with(['product', 'animal'])->where('lot_number', $term)->first();
        if ($this->lot !== null) {
            $this->affectedItems = OrderItem::query()->with('order')
                ->where('lot_id', $this->lot->id)
                ->whereNotNull('lot_id')
                ->get();

            return;
        }

        $this->order = Order::query()->with(['items.lot.animal'])->where('number', $term)->first();
    }
}
