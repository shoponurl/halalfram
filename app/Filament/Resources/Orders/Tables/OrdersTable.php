<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Tables;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\Cents;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('number')->label('Order')->searchable()->weight('bold'),
                TextColumn::make('created_at')->label('Placed')->dateTime('M j, g:i A')->sortable(),
                TextColumn::make('customer_name')->label('Customer')->searchable()
                    ->description(fn (Order $record) => $record->customer_phone),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (OrderStatus $state) => $state->label())
                    ->color(fn (OrderStatus $state) => $state->color()),
                TextColumn::make('estimated_cents')->label('Estimate')->formatStateUsing(fn (int $state) => Cents::format($state))->alignEnd(),
                TextColumn::make('hold_cents')->label('Hold')->formatStateUsing(fn (int $state) => Cents::format($state))->alignEnd(),
                TextColumn::make('final_cents')->label('Actual')->formatStateUsing(fn (?int $state) => Cents::format($state))->placeholder('—')->alignEnd(),
                TextColumn::make('authorization_expires_at')->label('Hold expires')->since()->placeholder('—')
                    ->color(fn (Order $record) => $record->authorization_expires_at?->lt(now()->addDays(2)) ? 'danger' : null),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(OrderStatus::cases())->mapWithKeys(fn (OrderStatus $s) => [$s->value => $s->label()])),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
