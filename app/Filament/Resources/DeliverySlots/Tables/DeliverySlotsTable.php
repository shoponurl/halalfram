<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeliverySlots\Tables;

use App\Enums\OrderStatus;
use App\Models\DeliverySlot;
use App\Models\Order;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class DeliverySlotsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('date')
            ->columns([
                TextColumn::make('date')->date()->sortable(),
                TextColumn::make('window')->label('Window')->state(fn (DeliverySlot $record) => $record->label()),
                TextColumn::make('capacity')->label('Capacity'),
                TextColumn::make('booked')->label('Booked')->state(
                    fn (DeliverySlot $record) => Order::query()->where('delivery_slot_id', $record->id)->whereNotIn('status', OrderStatus::abandoned())->count()
                ),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
        // No delete: past orders reference a slot, so it stays for the record even once it's in the past.
    }
}
