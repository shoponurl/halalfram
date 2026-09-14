<?php

declare(strict_types=1);

namespace App\Filament\Resources\Animals;

use App\Filament\Resources\Animals\Pages\CreateAnimal;
use App\Filament\Resources\Animals\Pages\EditAnimal;
use App\Filament\Resources\Animals\Pages\ListAnimals;
use App\Filament\Resources\Animals\Schemas\AnimalForm;
use App\Filament\Resources\Animals\Tables\AnimalsTable;
use App\Models\Animal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AnimalResource extends Resource
{
    protected static ?string $model = Animal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $recordTitleAttribute = 'tag_id';

    public static function form(Schema $schema): Schema
    {
        return AnimalForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AnimalsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnimals::route('/'),
            'create' => CreateAnimal::route('/create'),
            'edit' => EditAnimal::route('/{record}/edit'),
        ];
    }
}
