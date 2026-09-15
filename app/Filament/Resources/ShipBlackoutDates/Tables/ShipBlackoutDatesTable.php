<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShipBlackoutDates\Tables;

use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ShipBlackoutDatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date')
            ->columns([
                TextColumn::make('date')->date(),
                TextColumn::make('reason'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
