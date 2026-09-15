<?php

declare(strict_types=1);

namespace App\Filament\Resources\PrivacyRequests\Tables;

use App\Actions\Compliance\AnonymizeCustomerData;
use App\Enums\PrivacyRequestStatus;
use App\Enums\PrivacyRequestType;
use App\Models\PrivacyRequest;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PrivacyRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Filed')->dateTime(),
                BadgeColumn::make('type')->formatStateUsing(fn (PrivacyRequestType $state) => $state->label()),
                TextColumn::make('customer_email')->label('Email')->searchable(),
                TextColumn::make('customer_name')->label('Name')->placeholder('—'),
                TextColumn::make('note')->limit(40)->placeholder('—'),
                BadgeColumn::make('status')->formatStateUsing(fn (PrivacyRequestStatus $state) => $state->label())
                    ->color(fn (PrivacyRequestStatus $state) => $state === PrivacyRequestStatus::Fulfilled ? 'success' : 'warning'),
                TextColumn::make('fulfilled_at')->dateTime()->placeholder('—'),
            ])
            ->recordActions([
                Action::make('fulfill')
                    ->label(fn (PrivacyRequest $record) => $record->type === PrivacyRequestType::Delete ? 'Anonymize' : 'Mark sent')
                    ->icon('heroicon-o-check-circle')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription(fn (PrivacyRequest $record) => $record->type === PrivacyRequestType::Delete
                        ? "This scrubs the name, email, phone and address on every order for {$record->customer_email} — it can't be undone."
                        : "Confirm you've sent {$record->customer_email} a copy of their data outside this system.")
                    ->authorize(fn (PrivacyRequest $record) => auth()->user()?->can('fulfill', $record) ?? false)
                    ->visible(fn (PrivacyRequest $record) => $record->status === PrivacyRequestStatus::Pending)
                    ->action(function (PrivacyRequest $record): void {
                        /** @var User $user */
                        $user = auth()->user();
                        if ($record->type === PrivacyRequestType::Delete) {
                            app(AnonymizeCustomerData::class)->handle($record, $user);
                        } else {
                            $record->status = PrivacyRequestStatus::Fulfilled;
                            $record->fulfilled_by = $user->id;
                            $record->fulfilled_at = now();
                            $record->save();
                        }
                        Notification::make()->success()->title('Request fulfilled')->send();
                    }),
            ]);
    }
}
