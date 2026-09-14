<?php

declare(strict_types=1);

namespace App\Filament\Resources\Lots\Schemas;

use App\Enums\StorageLocation;
use App\Models\Animal;
use App\Models\Product;
use App\Support\Weight;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class LotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Receive stock')
                ->description('Opens a new lot and records its starting weight (guideline ch. 6, Sprint 03). Product, animal and pack date can\'t change after receiving — correct a mistake by withdrawing this lot and receiving a new one.')
                ->columns(2)
                ->schema([
                    Select::make('product_id')->label('Product')->options(fn () => Product::query()->active()->pluck('name', 'id'))
                        ->required()->searchable()->disabled(fn (string $operation) => $operation === 'edit')->dehydrated(),
                    Select::make('animal_id')->label('Animal')->relationship(name: 'animal', titleAttribute: 'tag_id')
                        ->options(fn () => Animal::query()->pluck('tag_id', 'id'))
                        ->searchable()->disabled(fn (string $operation) => $operation === 'edit')->dehydrated(),
                    Select::make('storage_location')->options(collect(StorageLocation::cases())->mapWithKeys(fn (StorageLocation $s) => [$s->value => $s->label()]))->required(),
                    DatePicker::make('pack_date')->required()->native(false)->disabled(fn (string $operation) => $operation === 'edit')->dehydrated(),
                    DatePicker::make('use_by_date')->required()->native(false),
                    TextInput::make('weight')
                        ->label('Starting weight (lb)')
                        ->required(fn (string $operation) => $operation === 'create')
                        ->visible(fn (string $operation) => $operation === 'create')
                        ->rule('regex:/^\d{1,5}(\.\d{1,3})?$/')
                        ->helperText('Dressed/sellable weight, not live weight.'),
                ]),
            Section::make('Current balance')
                ->visible(fn (string $operation) => $operation === 'edit')
                ->columns(2)
                ->schema([
                    TextInput::make('on_hand_weight_lb')->label('On hand (lb)')->disabled()->dehydrated(false)
                        ->formatStateUsing(fn ($state) => $state instanceof Weight ? $state->toDecimal() : $state),
                    TextInput::make('reserved_weight_lb')->label('Reserved (lb)')->disabled()->dehydrated(false)
                        ->formatStateUsing(fn ($state) => $state instanceof Weight ? $state->toDecimal() : $state),
                ]),
        ]);
    }
}
