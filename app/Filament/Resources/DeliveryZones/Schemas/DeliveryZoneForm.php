<?php

declare(strict_types=1);

namespace App\Filament\Resources\DeliveryZones\Schemas;

use App\Support\DollarInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class DeliveryZoneForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Delivery zone')
                ->description('Owner decision (guideline S05): a flat fee per zone, covering the actual delivery cost. Add zip codes on the zone\'s own page once it\'s saved.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required()->maxLength(120)->columnSpanFull(),
                    TextInput::make('flat_fee_cents')
                        ->label('Flat delivery fee ($)')
                        ->required()
                        ->rule('regex:/^\$?\d{1,4}(\.\d{1,2})?$/')
                        ->prefix('$')
                        ->default('0.00')
                        ->formatStateUsing(fn ($state) => is_int($state) ? DollarInput::fromCents($state) : $state)
                        ->dehydrateStateUsing(fn ($state) => DollarInput::toCents((string) $state)),
                    Toggle::make('is_active')->default(true)->inline(false),
                ]),
        ]);
    }
}
