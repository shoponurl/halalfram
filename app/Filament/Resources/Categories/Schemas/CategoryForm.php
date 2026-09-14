<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories\Schemas;

use App\Enums\Species;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Category')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required()->maxLength(120)->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, callable $set, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                    TextInput::make('slug')->required()->maxLength(120)->alphaDash()->unique(ignoreRecord: true),
                    Select::make('species')->options(collect(Species::cases())->mapWithKeys(fn (Species $s) => [$s->value => $s->label()]))->required(),
                    Toggle::make('supports_custom_cuts')->label('Offers cut/offal options')
                        ->helperText('Turn on for species with butcher-cut choices (guideline M03). Leave off for processed/deli items.')
                        ->inline(false),
                    TextInput::make('sort_order')->numeric()->default(0),
                ]),
        ]);
    }
}
