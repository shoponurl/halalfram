<?php

declare(strict_types=1);

namespace App\Filament\Resources\OffalOptions;

use App\Filament\Resources\OffalOptions\Pages\CreateOffalOption;
use App\Filament\Resources\OffalOptions\Pages\EditOffalOption;
use App\Filament\Resources\OffalOptions\Pages\ListOffalOptions;
use App\Filament\Resources\OffalOptions\Schemas\OffalOptionForm;
use App\Filament\Resources\OffalOptions\Tables\OffalOptionsTable;
use App\Models\OffalOption;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class OffalOptionResource extends Resource
{
    protected static ?string $model = OffalOption::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return OffalOptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OffalOptionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOffalOptions::route('/'),
            'create' => CreateOffalOption::route('/create'),
            'edit' => EditOffalOption::route('/{record}/edit'),
        ];
    }
}
