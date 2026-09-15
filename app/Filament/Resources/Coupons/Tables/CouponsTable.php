<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coupons\Tables;

use App\Support\Cents;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CouponsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->weight('bold'),
                TextColumn::make('type')->badge(),
                TextColumn::make('value')->formatStateUsing(fn (string $state, $record) => $record->type === 'fixed' ? Cents::format((int) $state) : "{$state}%"),
                TextColumn::make('redeemed_count')->label('Used')->formatStateUsing(fn (int $state, $record) => $record->max_redemptions !== null ? "{$state} / {$record->max_redemptions}" : (string) $state),
                TextColumn::make('expires_at')->dateTime()->placeholder('Never'),
                IconColumn::make('active')->boolean(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }
}
