<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Enums\DeliveryEventType;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Append-only fulfilment history (rule 04, guideline ch. 6 Sprint 05). */
class DeliveryEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'deliveryEvents';

    protected static ?string $title = 'Fulfilment history';

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
            ->defaultSort('id', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('recorder'))
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('M j, g:i A'),
                TextColumn::make('type')->formatStateUsing(fn (DeliveryEventType $state) => $state->label()),
                TextColumn::make('recorder.name')->label('By')->placeholder('System'),
                TextColumn::make('note')->placeholder('—')->wrap(),
            ])
            ->recordActions([])
            ->emptyStateHeading('No fulfilment activity yet');
    }
}
