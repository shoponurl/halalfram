<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Tables;

use App\Enums\Species;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->searchable()->weight('bold'),
                TextColumn::make('species')->formatStateUsing(fn (Species $state) => $state->label()),
                TextColumn::make('products_count')->counts('products')->label('Products'),
                IconColumn::make('supports_custom_cuts')->label('Cut options')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
        // No delete: products and options may still reference a category, so it's switched off instead.
    }
}
