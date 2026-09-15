<?php

declare(strict_types=1);

namespace App\Filament\Resources\NotificationTemplates\Tables;

use App\Enums\NotificationEvent;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NotificationTemplatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event')->formatStateUsing(fn (NotificationEvent $state) => $state->label())->searchable(),
                TextColumn::make('channel')->badge()->formatStateUsing(fn (string $state) => mb_strtoupper($state)),
                TextColumn::make('subject')->placeholder('—')->limit(40),
                TextColumn::make('body')->limit(60),
                IconColumn::make('active')->boolean(),
            ])
            ->defaultSort('event')
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
