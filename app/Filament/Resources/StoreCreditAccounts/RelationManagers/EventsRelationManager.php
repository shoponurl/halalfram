<?php

declare(strict_types=1);

namespace App\Filament\Resources\StoreCreditAccounts\RelationManagers;

use App\Support\Cents;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Read-only, append-only ledger behind the account's cached balance (rule 04). */
class EventsRelationManager extends RelationManager
{
    protected static string $relationship = 'events';

    protected static ?string $title = 'History';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime(),
                TextColumn::make('type')->badge(),
                TextColumn::make('amount_cents')->label('Amount')->formatStateUsing(fn (int $state) => ($state >= 0 ? '+' : '−').Cents::format(abs($state))),
                TextColumn::make('reason')->placeholder('—'),
                TextColumn::make('order.number')->label('Order')->placeholder('—'),
                TextColumn::make('issuedBy.name')->label('By')->placeholder('—'),
            ])
            ->recordActions([]);
    }
}
