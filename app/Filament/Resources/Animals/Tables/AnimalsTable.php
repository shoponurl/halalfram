<?php

declare(strict_types=1);

namespace App\Filament\Resources\Animals\Tables;

use App\Enums\Species;
use App\Models\Animal;
use App\Support\Weight;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AnimalsTable
{
    public static function configure(Table $table): Table
    {
        $lb = fn (?Weight $state) => $state === null ? '—' : rtrim(rtrim($state->toDecimal(), '0'), '.').' lb';

        return $table
            ->defaultSort('slaughter_date', 'desc')
            ->columns([
                TextColumn::make('tag_id')->label('Tag')->searchable()->weight('bold'),
                TextColumn::make('species')->formatStateUsing(fn (Species $state) => $state->label()),
                TextColumn::make('slaughter_date')->date(),
                TextColumn::make('live_weight_lb')->label('Live weight')->formatStateUsing($lb),
                TextColumn::make('dressed_weight_lb')->label('Dressed weight')->formatStateUsing($lb),
                TextColumn::make('dressing_loss')->label('Dressing loss')->state(fn (Animal $record) => $lb($record->dressingLoss())),
                TextColumn::make('lots_count')->counts('lots')->label('Lots'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
        // No delete: the traceability register is permanent.
    }
}
