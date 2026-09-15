<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeliveryZones\RelationManagers;

use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/** Owner decision (guideline ch. 7, S05): the service area is an explicit zip list, not a radius. */
class ZipsRelationManager extends RelationManager
{
    protected static string $relationship = 'zips';

    protected static ?string $title = 'Zip codes';

    protected static ?string $recordTitleAttribute = 'zip_code';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('zip_code')->label('Zip code')->required()->maxLength(10)->rule('regex:/^\d{5}$/')->unique(ignoreRecord: true),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('zip_code')
            ->columns([
                TextColumn::make('zip_code')->label('Zip code')->searchable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                DeleteAction::make(),
            ]);
    }
}
