<?php

declare(strict_types=1);

namespace App\Filament\Resources\PackingOptions\Pages;

use App\Filament\Resources\PackingOptions\PackingOptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPackingOptions extends ListRecords
{
    protected static string $resource = PackingOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
