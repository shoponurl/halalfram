<?php

declare(strict_types=1);

namespace App\Filament\Resources\OffalOptions\Pages;

use App\Filament\Resources\OffalOptions\OffalOptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOffalOptions extends ListRecords
{
    protected static string $resource = OffalOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
