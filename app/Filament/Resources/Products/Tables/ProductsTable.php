<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Tables;

use App\Models\Product;
use App\Support\Cents;
use App\Support\Weight;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->weight('bold')->description(fn (Product $record) => '/products/'.$record->slug),
                TextColumn::make('category.name')->label('Category')->placeholder('—')->sortable(),
                TextColumn::make('price_per_lb_cents')->label('Price / lb')->formatStateUsing(fn (int $state) => Cents::format($state)),
                TextColumn::make('estimated_weight_lb')->label('Est. weight')->formatStateUsing(fn (Weight $state) => rtrim(rtrim($state->toDecimal(), '0'), '.').' lb'),
                TextColumn::make('estimate')->label('Est. price / piece')->state(fn (Product $record) => Cents::format($record->estimatedPieceCents())),
                TextColumn::make('tolerance_pct')->label('Tolerance')->formatStateUsing(fn (?string $state) => ($state ?? config('catchweight.hold_tolerance_pct')).'%')
                    ->placeholder(config('catchweight.hold_tolerance_pct').'% (store)'),
                IconColumn::make('is_active')->label('On sale')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
        // No delete: products on past orders are switched off, never removed.
    }
}
