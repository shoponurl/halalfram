<?php

declare(strict_types=1);

namespace App\Filament\Resources\CutOptions\Tables;

use App\Enums\CutStyle;
use App\Support\Cents;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CutOptionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('category.name')->label('Category')->sortable(),
                TextColumn::make('name')->searchable()->weight('bold'),
                TextColumn::make('cut_style')->formatStateUsing(fn (CutStyle $state) => $state->label()),
                TextColumn::make('extra_price_cents')->label('Extra price')->formatStateUsing(fn (int $state) => Cents::format($state)),
                TextColumn::make('extra_lead_time_days')->label('Extra lead time')->formatStateUsing(fn (int $state) => $state.'d'),
                TextColumn::make('raw_yield_pct')->label('Raw yield')->placeholder('no loss modeled')->formatStateUsing(fn (?string $state) => $state === null ? null : "{$state}%"),
                TextColumn::make('estimated_minutes')->label('Butcher-min')->placeholder((string) config('catchweight.default_processing_minutes').' (default)'),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
        // No delete: past orders snapshot the option's name/price, so the row is switched off instead.
    }
}
