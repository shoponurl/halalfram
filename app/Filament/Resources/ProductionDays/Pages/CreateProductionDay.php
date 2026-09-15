<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductionDays\Pages;

use App\Filament\Resources\ProductionDays\ProductionDayResource;
use Filament\Resources\Pages\CreateRecord;

class CreateProductionDay extends CreateRecord
{
    protected static string $resource = ProductionDayResource::class;
}
