<?php

declare(strict_types=1);

namespace App\Filament\Resources\PackingOptions\Pages;

use App\Filament\Resources\PackingOptions\PackingOptionResource;
use Filament\Resources\Pages\EditRecord;

class EditPackingOption extends EditRecord
{
    protected static string $resource = PackingOptionResource::class;
}
