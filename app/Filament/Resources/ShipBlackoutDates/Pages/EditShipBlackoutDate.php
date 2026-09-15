<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShipBlackoutDates\Pages;

use App\Filament\Resources\ShipBlackoutDates\ShipBlackoutDateResource;
use Filament\Resources\Pages\EditRecord;

class EditShipBlackoutDate extends EditRecord
{
    protected static string $resource = ShipBlackoutDateResource::class;
}
