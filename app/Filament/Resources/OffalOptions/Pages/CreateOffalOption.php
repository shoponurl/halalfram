<?php

declare(strict_types=1);

namespace App\Filament\Resources\OffalOptions\Pages;

use App\Filament\Resources\OffalOptions\OffalOptionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOffalOption extends CreateRecord
{
    protected static string $resource = OffalOptionResource::class;
}
