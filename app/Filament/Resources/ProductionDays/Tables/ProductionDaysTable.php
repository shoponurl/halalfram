<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductionDays\Tables;

use App\Models\ProductionDay;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductionDaysTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date', 'desc')
            ->columns([
                TextColumn::make('date')->date()->weight('bold'),
                TextColumn::make('capacity')->label('Capacity (min)')->state(fn (ProductionDay $record) => $record->capacityMinutes())
                    ->description(fn (ProductionDay $record) => $record->capacity_minutes === null ? 'shop default' : 'override'),
                IconColumn::make('is_open')->label('Open')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
        // No delete: a day with orders scheduled against it must stay for the capacity math to hold.
    }
}
