<?php

declare(strict_types=1);

namespace App\Filament\Resources\Orders\RelationManagers;

use App\Models\QcCheck;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/** Append-only QC history (rule 04): a failed check is never edited, only followed by a fresh one. */
class QcChecksRelationManager extends RelationManager
{
    protected static string $relationship = 'qcChecks';

    protected static ?string $title = 'QC checks';

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
            ->modifyQueryUsing(fn ($query) => $query->with('inspector'))
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('M j, g:i:s A'),
                IconColumn::make('passed')->label('Result')->boolean(),
                TextColumn::make('temperature_f')->label('Temp (°F)')->placeholder('—'),
                TextColumn::make('checklist')->label('Checklist')->state(
                    fn (QcCheck $record) => collect($record->checklist)->filter()->keys()->map(fn ($key) => str($key)->replace('_', ' ')->headline())->implode(', ') ?: '—'
                )->wrap(),
                TextColumn::make('inspector.name')->label('Inspector'),
                TextColumn::make('note')->placeholder('—')->wrap(),
            ])
            ->recordActions([])
            ->emptyStateHeading('No QC checks recorded yet')
            ->recordTitle(fn (QcCheck $record) => "QC check #{$record->id}");
    }
}
