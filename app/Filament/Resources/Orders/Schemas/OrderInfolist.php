<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\Schemas;

use App\Enums\FulfilmentStatus;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Support\Cents;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OrderInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $money = fn (?int $state) => Cents::format($state);

        return $schema
            ->columns(3)
            ->components([
                Section::make('Payment')
                    ->columnSpan(2)
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')->badge()
                            ->formatStateUsing(fn (OrderStatus $state) => $state->label())
                            ->color(fn (OrderStatus $state) => $state->color()),
                        TextEntry::make('scheduled_date')->label('Scheduled for')->date('D, M j')->placeholder('—'),
                        TextEntry::make('estimated_cents')->label('Estimate')->formatStateUsing($money),
                        TextEntry::make('hold_cents')
                            ->label(fn (Order $record) => 'Card hold (estimate + '.rtrim(rtrim($record->hold_tolerance_pct, '0'), '.').'%)')
                            ->formatStateUsing($money),
                        TextEntry::make('final_cents')->label('Actual total')->formatStateUsing($money)->placeholder('Not finalized'),
                        TextEntry::make('captured_cents')->label('Captured from hold')->formatStateUsing($money),
                        TextEntry::make('extra_charged_cents')->label('Charged above hold')->formatStateUsing($money),
                        TextEntry::make('balance_due_cents')->label('Balance due')->formatStateUsing($money)
                            ->color(fn (int $state) => $state > 0 ? 'danger' : null),
                        TextEntry::make('balance_paid_cents')->label('Balance paid')->formatStateUsing($money),
                        TextEntry::make('written_off_cents')->label('Written off')->formatStateUsing($money),
                        TextEntry::make('authorization_expires_at')->label('Hold expires')->dateTime('M j, g:i A')->placeholder('—'),
                        TextEntry::make('balance_payment_url')->label('Balance payment link')->url(fn (?string $state) => $state)
                            ->openUrlInNewTab()->copyable()->placeholder('—')->columnSpan(2),
                    ]),
                Section::make('Customer')
                    ->schema([
                        TextEntry::make('customer_name')->label('Name'),
                        TextEntry::make('customer_phone')->label('Phone')->url(fn (string $state) => 'tel:'.$state),
                        TextEntry::make('customer_email')->label('Email')->url(fn (string $state) => 'mailto:'.$state),
                        TextEntry::make('notes')->placeholder('—'),
                        TextEntry::make('stripe_payment_intent_id')->label('Stripe payment')
                            ->url(fn (?string $state) => $state ? "https://dashboard.stripe.com/payments/{$state}" : null)
                            ->openUrlInNewTab()->placeholder('—'),
                    ]),
                Section::make('Fulfilment')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('fulfilment')->label('Method')->formatStateUsing(fn (string $state) => ucfirst($state)),
                        TextEntry::make('fulfilment_status')->label('Status')->badge()
                            ->formatStateUsing(fn (FulfilmentStatus $state) => $state->label())
                            ->color(fn (FulfilmentStatus $state) => $state->color()),
                        TextEntry::make('refunded_cents')->label('Refunded')->formatStateUsing($money)->visible(fn (Order $record) => $record->refunded_cents > 0),
                        TextEntry::make('delivery_address')->label('Address')->state(fn (Order $record) => $record->fullDeliveryAddress())
                            ->visible(fn (Order $record) => $record->fulfilment === 'delivery')->columnSpan(2),
                        TextEntry::make('deliveryZone.name')->label('Zone / fee')
                            ->formatStateUsing(fn (?string $state, Order $record) => trim(($state ?? '—').' · '.Cents::format($record->delivery_fee_cents)))
                            ->visible(fn (Order $record) => $record->fulfilment === 'delivery'),
                        TextEntry::make('deliverySlot.label')->label('Window')->state(fn (Order $record) => $record->deliverySlot?->label() ?? '—')
                            ->visible(fn (Order $record) => $record->fulfilment === 'delivery'),
                        TextEntry::make('driver.name')->label('Driver')->placeholder('—')
                            ->visible(fn (Order $record) => $record->fulfilment === 'delivery'),
                        TextEntry::make('delivery_otp')->label('Customer code')->placeholder('—')
                            ->visible(fn (Order $record) => $record->fulfilment_status === FulfilmentStatus::OutForDelivery),
                    ]),
                Section::make('Policy snapshot at checkout')
                    ->columnSpanFull()
                    ->columns(3)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('hold_tolerance_pct')->label('Hold tolerance')->suffix('%'),
                        TextEntry::make('overage_autocharge_pct')->label('Auto-charge up to estimate +')->suffix('%'),
                        TextEntry::make('underweight_review_pct')->label('Manager review below estimate −')->suffix('%'),
                    ]),
            ]);
    }
}
