<?php

declare(strict_types=1);

namespace App\Filament\Resources\PackingOptions\Tables;

use App\Support\Cents;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PackingOptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->searchable()->weight('bold'),
                TextColumn::make('surcharge_cents')->label('Surcharge')->formatStateUsing(fn (int $state) => Cents::format($state)),
                TextColumn::make('extra_lead_time_days')->label('Extra lead time')->formatStateUsing(fn (int $state) => $state.'d'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
