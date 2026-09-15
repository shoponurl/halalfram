<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeliverySlots\Pages;

use App\Filament\Resources\DeliverySlots\DeliverySlotResource;
use Filament\Resources\Pages\EditRecord;

class EditDeliverySlot extends EditRecord
{
    protected static string $resource = DeliverySlotResource::class;
}
