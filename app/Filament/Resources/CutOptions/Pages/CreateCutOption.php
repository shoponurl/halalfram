<?php

declare(strict_types=1);

namespace App\Filament\Resources\CutOptions\Pages;

use App\Filament\Resources\CutOptions\CutOptionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCutOption extends CreateRecord
{
    protected static string $resource = CutOptionResource::class;
}
