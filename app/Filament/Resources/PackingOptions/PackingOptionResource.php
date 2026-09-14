<?php

declare(strict_types=1);

namespace App\Filament\Resources\PackingOptions;

use App\Filament\Resources\PackingOptions\Pages\CreatePackingOption;
use App\Filament\Resources\PackingOptions\Pages\EditPackingOption;
use App\Filament\Resources\PackingOptions\Pages\ListPackingOptions;
use App\Filament\Resources\PackingOptions\Schemas\PackingOptionForm;
use App\Filament\Resources\PackingOptions\Tables\PackingOptionsTable;
use App\Models\PackingOption;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PackingOptionResource extends Resource
{
    protected static ?string $model = PackingOption::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return PackingOptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PackingOptionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPackingOptions::route('/'),
            'create' => CreatePackingOption::route('/create'),
            'edit' => EditPackingOption::route('/{record}/edit'),
        ];
    }
}
