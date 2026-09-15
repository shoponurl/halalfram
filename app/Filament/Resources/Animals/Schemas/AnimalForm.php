<?php

declare(strict_types=1);

namespace App\Filament\Resources\Animals\Schemas;

use App\Enums\AnimalCostSource;
use App\Enums\Species;
use App\Support\DollarInput;
use App\Support\Weight;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AnimalForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Animal')
                ->description('The minimum traceability register (guideline M02) — one tag, one slaughter date, the source a lot and every order can be traced back to.')
                ->columns(2)
                ->schema([
                    TextInput::make('tag_id')->label('Tag ID')->required()->maxLength(40)->unique(ignoreRecord: true)->placeholder('F145'),
                    Select::make('species')->options(collect(Species::cases())->mapWithKeys(fn (Species $s) => [$s->value => $s->label()]))->required(),
                    DatePicker::make('slaughter_date')->required()->native(false),
                    TextInput::make('live_weight_lb')
                        ->label('Live weight (lb)')
                        ->rule('regex:/^\d{1,5}(\.\d{1,3})?$/')
                        ->formatStateUsing(fn ($state) => $state instanceof Weight ? $state->toDecimal() : $state)
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? Weight::pounds((string) $state)->toDecimal() : null),
                    TextInput::make('dressed_weight_lb')
                        ->label('Dressed weight (lb)')
                        ->rule('regex:/^\d{1,5}(\.\d{1,3})?$/')
                        ->formatStateUsing(fn ($state) => $state instanceof Weight ? $state->toDecimal() : $state)
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? Weight::pounds((string) $state)->toDecimal() : null),
                    Textarea::make('notes')->rows(2)->columnSpanFull(),
                ]),
            Section::make('Cost')
                ->description('Owner decision (guideline ch. 7, S07): own-farm animals have no purchase invoice, so their cost is always a manual estimate — the margin report flags it as such, never as real, invoiced cost.')
                ->columns(2)
                ->schema([
                    Select::make('cost_source')
                        ->options(collect(AnimalCostSource::cases())->mapWithKeys(fn (AnimalCostSource $s) => [$s->value => $s->label()]))
                        ->default(AnimalCostSource::Purchased->value)
                        ->required(),
                    TextInput::make('cost_cents')
                        ->label('Cost ($, optional)')
                        ->prefix('$')
                        ->rule('nullable|regex:/^\$?\d{1,6}(\.\d{1,2})?$/')
                        ->formatStateUsing(fn ($state) => is_int($state) ? DollarInput::fromCents($state) : $state)
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? DollarInput::toCents((string) $state) : null),
                ]),
        ]);
    }
}
