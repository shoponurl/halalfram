<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Enums\PaymentTransactionType;
use App\Models\PaymentTransaction;
use App\Support\Cents;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Payment ledger with the idempotency key sent to Stripe for each step. */
class TransactionsRelationManager extends RelationManager
{
    protected static string $relationship = 'transactions';

    protected static ?string $title = 'Payment ledger';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return auth()->user()?->can('view', $ownerRecord) ?? false;
    }

    public function isReadOnly(): bool
    {
        return true;
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('id')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('M j, g:i:s A'),
                TextColumn::make('type')->badge()->formatStateUsing(fn (PaymentTransactionType $state) => str_replace('_', ' ', ucfirst($state->value))),
                TextColumn::make('status')->badge()->color(fn (string $state) => match ($state) {
                    PaymentTransaction::SUCCEEDED => 'success',
                    PaymentTransaction::FAILED => 'danger',
                    default => 'gray',
                }),
                TextColumn::make('amount_cents')->label('Amount')->formatStateUsing(fn (int $state) => Cents::format($state))->alignEnd(),
                TextColumn::make('stripe_object_id')->label('Stripe ID')->copyable()->placeholder('—'),
                TextColumn::make('failure_message')->label('Failure')->placeholder('—')->wrap()->color('danger'),
                TextColumn::make('idempotency_key')->label('Idempotency key')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([]);
    }
}
