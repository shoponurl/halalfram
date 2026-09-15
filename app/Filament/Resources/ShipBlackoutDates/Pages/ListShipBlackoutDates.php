<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShipBlackoutDates\Pages;

use App\Filament\Resources\ShipBlackoutDates\ShipBlackoutDateResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListShipBlackoutDates extends ListRecords
{
    protected static string $resource = ShipBlackoutDateResource::class;

    /** @return array<CreateAction> */
    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
