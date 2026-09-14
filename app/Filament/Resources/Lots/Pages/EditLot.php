<?php

declare(strict_types=1);

namespace App\Filament\Resources\Lots\Pages;

use App\Filament\Resources\Lots\LotResource;
use Filament\Resources\Pages\EditRecord;

class EditLot extends EditRecord
{
    protected static string $resource = LotResource::class;
}
