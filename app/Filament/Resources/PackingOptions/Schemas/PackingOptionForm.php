<?php

declare(strict_types=1);

namespace App\Filament\Resources\PackingOptions\Schemas;

use App\Support\DollarInput;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PackingOptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Packing option')
                ->description('Applies across the catalog: vacuum pack, portion size, etc.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required()->maxLength(120)->columnSpanFull(),
                    TextInput::make('surcharge_cents')
                        ->label('Surcharge ($/piece)')
                        ->required()
                        ->rule('regex:/^\$?\d{1,5}(\.\d{1,2})?$/')
                        ->prefix('$')
                        ->default('0.00')
                        ->formatStateUsing(fn ($state) => is_int($state) ? DollarInput::fromCents($state) : $state)
                        ->dehydrateStateUsing(fn ($state) => DollarInput::toCents((string) $state)),
                    TextInput::make('extra_lead_time_days')->label('Extra lead time (days)')->numeric()->default(0)->minValue(0),
                    Toggle::make('is_active')->default(true)->inline(false),
                    TextInput::make('sort_order')->numeric()->default(0),
                ]),
        ]);
    }
}
