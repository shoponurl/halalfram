<?php

declare(strict_types=1);

namespace App\Filament\Resources\ShipBlackoutDates\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ShipBlackoutDateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Ship blackout date')
                ->description('Guideline ch. 6, S08: on top of the standing Monday-Thursday ship rule, block specific dates — federal holidays and the like.')
                ->schema([
                    DatePicker::make('date')->required()->native(false)->unique(ignoreRecord: true),
                    TextInput::make('reason')->required()->maxLength(190),
                ]),
        ]);
    }
}
