<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeliverySlots\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DeliverySlotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Delivery window')
                ->description('Owner decision (guideline S05): kept short enough that a delivery never sits outside refrigeration past config(\'catchweight.cold_chain_max_hours\').')
                ->columns(2)
                ->schema([
                    DatePicker::make('date')->required()->native(false),
                    TextInput::make('capacity')->label('Deliveries this window can handle')->required()->numeric()->minValue(1)->default(1),
                    TimePicker::make('start_time')->required()->seconds(false),
                    TimePicker::make('end_time')->required()->seconds(false)->after('start_time'),
                ]),
        ]);
    }
}
