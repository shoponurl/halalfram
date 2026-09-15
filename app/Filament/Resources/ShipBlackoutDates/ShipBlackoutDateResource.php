<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShipBlackoutDates;

use App\Filament\Resources\ShipBlackoutDates\Pages\CreateShipBlackoutDate;
use App\Filament\Resources\ShipBlackoutDates\Pages\EditShipBlackoutDate;
use App\Filament\Resources\ShipBlackoutDates\Pages\ListShipBlackoutDates;
use App\Filament\Resources\ShipBlackoutDates\Schemas\ShipBlackoutDateForm;
use App\Filament\Resources\ShipBlackoutDates\Tables\ShipBlackoutDatesTable;
use App\Models\ShipBlackoutDate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ShipBlackoutDateResource extends Resource
{
    protected static ?string $model = ShipBlackoutDate::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Ship blackout dates';

    protected static string|UnitEnum|null $navigationGroup = 'Shipping';

    protected static ?string $recordTitleAttribute = 'reason';

    public static function form(Schema $schema): Schema
    {
        return ShipBlackoutDateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ShipBlackoutDatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListShipBlackoutDates::route('/'),
            'create' => CreateShipBlackoutDate::route('/create'),
            'edit' => EditShipBlackoutDate::route('/{record}/edit'),
        ];
    }
}
