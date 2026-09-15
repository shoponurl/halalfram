<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductionDays\Pages;

use App\Filament\Resources\ProductionDays\ProductionDayResource;
use Filament\Resources\Pages\EditRecord;

class EditProductionDay extends EditRecord
{
    protected static string $resource = ProductionDayResource::class;
}
