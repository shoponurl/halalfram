<?php

declare(strict_types=1);

namespace App\Filament\Resources\PackingRules\Tables;

use App\Enums\PackageTemperature;
use App\Support\Weight;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PackingRulesTable
{
    public static function configure(Table $table): Table
    {
        $lb = fn (?Weight $state) => $state === null ? '—' : rtrim(rtrim($state->toDecimal(), '0'), '.').' lb';

        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->searchable()->weight('bold'),
                TextColumn::make('temperature')->badge()->formatStateUsing(fn (PackageTemperature $state) => $state->label()),
                TextColumn::make('min_weight_lb')->label('Min')->formatStateUsing($lb),
                TextColumn::make('max_weight_lb')->label('Max')->formatStateUsing($lb),
                TextColumn::make('tare_weight_lb')->label('Tare')->formatStateUsing($lb),
                TextColumn::make('dry_ice_lb')->label('Dry ice')->formatStateUsing($lb),
                IconColumn::make('is_active')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
        // No delete: past orders may still reference a rule, so it's switched off instead.
    }
}
