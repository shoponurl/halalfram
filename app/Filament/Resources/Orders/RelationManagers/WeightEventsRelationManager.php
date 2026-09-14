<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Enums\WeightSource;
use App\Models\WeightEvent;
use App\Support\Weight;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Append-only weighing history: who weighed what, when, on which scale (rule 04). */
class WeightEventsRelationManager extends RelationManager
{
    protected static string $relationship = 'weightEvents';

    protected static ?string $title = 'Weight history';

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
            ->modifyQueryUsing(fn ($query) => $query->with(['recorder', 'orderItem']))
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('M j, g:i:s A'),
                TextColumn::make('orderItem.product_name')->label('Item'),
                TextColumn::make('weight_lb')->label('Weight')->formatStateUsing(fn (Weight $state) => rtrim(rtrim($state->toDecimal(), '0'), '.').' lb'),
                TextColumn::make('source')->badge()->formatStateUsing(fn (WeightSource $state) => ucfirst($state->value)),
                TextColumn::make('recorder.name')->label('Recorded by'),
                TextColumn::make('note')->placeholder('—')->wrap(),
            ])
            ->recordActions([])
            ->emptyStateHeading('No weights recorded yet')
            ->recordTitle(fn (WeightEvent $record) => "Weighing #{$record->id}");
    }
}
