<?php

declare(strict_types=1);

namespace App\Filament\Resources\CutOptions\Pages;

use App\Filament\Resources\CutOptions\CutOptionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCutOptions extends ListRecords
{
    protected static string $resource = CutOptionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
