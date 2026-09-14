<?php

declare(strict_types=1);

namespace App\Filament\Resources\OffalOptions\Pages;

use App\Filament\Resources\OffalOptions\OffalOptionResource;
use Filament\Resources\Pages\EditRecord;

class EditOffalOption extends EditRecord
{
    protected static string $resource = OffalOptionResource::class;
}
