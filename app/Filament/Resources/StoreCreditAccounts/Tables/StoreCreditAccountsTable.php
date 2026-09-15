<?php

declare(strict_types=1);

namespace App\Filament\Resources\StoreCreditAccounts\Tables;

use App\Support\Cents;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class StoreCreditAccountsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('customer_email')->label('Customer')->searchable(),
                TextColumn::make('balance_cents')->label('Balance')->formatStateUsing(fn (int $state) => Cents::format($state))->sortable(),
                TextColumn::make('updated_at')->label('Last activity')->dateTime()->since(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
