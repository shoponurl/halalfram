<?php

declare(strict_types=1);

namespace App\Filament\Resources\OffalOptions\Tables;

use App\Support\Cents;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OffalOptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('category.name')->label('Category')->sortable(),
                TextColumn::make('name')->searchable()->weight('bold'),
                TextColumn::make('extra_price_cents')->label('Extra price')->formatStateUsing(fn (int $state) => Cents::format($state)),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
