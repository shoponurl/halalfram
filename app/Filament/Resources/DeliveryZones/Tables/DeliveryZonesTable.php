<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeliveryZones\Tables;

use App\Support\Cents;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeliveryZonesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->weight('bold'),
                TextColumn::make('flat_fee_cents')->label('Fee')->formatStateUsing(fn (int $state) => Cents::format($state)),
                TextColumn::make('zips_count')->counts('zips')->label('Zip codes'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
        // No delete: zips and past orders may still reference a zone, so it's switched off instead.
    }
}
