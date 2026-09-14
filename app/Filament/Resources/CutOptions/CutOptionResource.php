<?php

declare(strict_types=1);

namespace App\Filament\Resources\CutOptions;

use App\Filament\Resources\CutOptions\Pages\CreateCutOption;
use App\Filament\Resources\CutOptions\Pages\EditCutOption;
use App\Filament\Resources\CutOptions\Pages\ListCutOptions;
use App\Filament\Resources\CutOptions\Schemas\CutOptionForm;
use App\Filament\Resources\CutOptions\Tables\CutOptionsTable;
use App\Models\CutOption;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CutOptionResource extends Resource
{
    protected static ?string $model = CutOption::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScissors;

    protected static string|UnitEnum|null $navigationGroup = 'Catalog';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return CutOptionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CutOptionsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCutOptions::route('/'),
            'create' => CreateCutOption::route('/create'),
            'edit' => EditCutOption::route('/{record}/edit'),
        ];
    }
}
