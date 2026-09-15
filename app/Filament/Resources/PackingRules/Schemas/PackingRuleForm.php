<?php

declare(strict_types=1);

namespace App\Filament\Resources\PackingRules\Schemas;

use App\Enums\PackageTemperature;
use App\Support\Weight;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PackingRuleForm
{
    public static function configure(Schema $schema): Schema
    {
        $weightField = fn (string $name, string $label) => TextInput::make($name)
            ->label($label)
            ->required()
            ->rule('regex:/^\d{1,4}(\.\d{1,3})?$/')
            ->formatStateUsing(fn ($state) => $state instanceof Weight ? $state->toDecimal() : $state)
            ->dehydrateStateUsing(fn ($state) => filled($state) ? Weight::pounds((string) $state)->toDecimal() : null);

        return $schema->components([
            Section::make('Packing rule')
                ->description('Owner decision (guideline ch. 7, S08): packing rules live here, not in code, so they can change without a deploy.')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required()->maxLength(80)->columnSpanFull(),
                    Select::make('temperature')
                        ->options(collect(PackageTemperature::cases())->mapWithKeys(fn (PackageTemperature $t) => [$t->value => $t->label()]))
                        ->required(),
                    TextInput::make('sort_order')->numeric()->default(0),
                    $weightField('min_weight_lb', 'Min order weight (lb)'),
                    $weightField('max_weight_lb', 'Max order weight (lb)'),
                    $weightField('tare_weight_lb', 'Box + insulation tare weight (lb)'),
                    TextInput::make('dry_ice_lb')
                        ->label('Dry ice (lb, optional)')
                        ->rule('nullable|regex:/^\d{1,4}(\.\d{1,3})?$/')
                        ->formatStateUsing(fn ($state) => $state instanceof Weight ? $state->toDecimal() : $state)
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? Weight::pounds((string) $state)->toDecimal() : null),
                    TextInput::make('box_length_in')->label('Box length (in)')->numeric()->required(),
                    TextInput::make('box_width_in')->label('Box width (in)')->numeric()->required(),
                    TextInput::make('box_height_in')->label('Box height (in)')->numeric()->required(),
                    Toggle::make('is_active')->default(true)->inline(false),
                ]),
        ]);
    }
}
