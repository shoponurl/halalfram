<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductionDays\Pages;

use App\Filament\Resources\ProductionDays\ProductionDayResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProductionDays extends ListRecords
{
    protected static string $resource = ProductionDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
