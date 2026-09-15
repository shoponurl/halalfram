<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProductionDays;

use App\Filament\Resources\ProductionDays\Pages\CreateProductionDay;
use App\Filament\Resources\ProductionDays\Pages\EditProductionDay;
use App\Filament\Resources\ProductionDays\Pages\ListProductionDays;
use App\Filament\Resources\ProductionDays\Schemas\ProductionDayForm;
use App\Filament\Resources\ProductionDays\Tables\ProductionDaysTable;
use App\Models\ProductionDay;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ProductionDayResource extends Resource
{
    protected static ?string $model = ProductionDay::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Inventory';

    protected static ?string $recordTitleAttribute = 'date';

    public static function form(Schema $schema): Schema
    {
        return ProductionDayForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProductionDaysTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProductionDays::route('/'),
            'create' => CreateProductionDay::route('/create'),
            'edit' => EditProductionDay::route('/{record}/edit'),
        ];
    }
}
