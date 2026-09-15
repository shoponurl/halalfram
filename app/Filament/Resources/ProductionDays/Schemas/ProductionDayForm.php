<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductionDays\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProductionDayForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Production day')
                ->description('Override the shop\'s default daily butcher-minutes budget for one date, or close it entirely (owner decision, guideline ch. 7 S04).')
                ->columns(2)
                ->schema([
                    DatePicker::make('date')->required()->native(false)->disabled(fn (string $operation) => $operation === 'edit')->dehydrated(),
                    TextInput::make('capacity_minutes')
                        ->label('Capacity (butcher-minutes)')
                        ->placeholder((string) config('catchweight.daily_capacity_minutes'))
                        ->helperText('Leave empty to use the shop default.')
                        ->numeric()->minValue(0),
                    Toggle::make('is_open')->label('Open for scheduling')->default(true)->inline(false),
                ]),
        ]);
    }
}
