<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditLogs\Tables;

use App\Models\AuditLog;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AuditLogsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime(),
                TextColumn::make('causer.name')->label('Staff')->placeholder('System'),
                TextColumn::make('event')->badge(),
                TextColumn::make('description')->wrap(),
                TextColumn::make('auditable_type')->label('Record')->formatStateUsing(fn (?string $state, AuditLog $record) => $state === null ? null : class_basename($state).' #'.$record->auditable_id)->placeholder('—'),
            ]);
    }
}
