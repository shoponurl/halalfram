<?php

declare(strict_types=1);

namespace App\Filament\Resources\CutOptions\Schemas;

use App\Enums\CutStyle;
use App\Models\Category;
use App\Support\DollarInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CutOptionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Cut option')
                ->columns(2)
                ->schema([
                    Select::make('category_id')->label('Category')->relationship(name: 'category', titleAttribute: 'name')
                        ->options(fn () => Category::query()->where('supports_custom_cuts', true)->pluck('name', 'id'))
                        ->required()->searchable(),
                    Select::make('cut_style')->options(collect(CutStyle::cases())->mapWithKeys(fn (CutStyle $s) => [$s->value => $s->label()]))->required(),
                    TextInput::make('name')->required()->maxLength(120)->columnSpanFull(),
                    TextInput::make('extra_price_cents')
                        ->label('Extra price ($/piece)')
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
