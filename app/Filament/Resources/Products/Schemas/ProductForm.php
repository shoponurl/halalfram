<?php

declare(strict_types=1);

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\PortionType;
use App\Support\DollarInput;
use App\Support\Weight;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Product')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required()->maxLength(160)->live(onBlur: true)
                        ->afterStateUpdated(fn ($state, callable $set, string $operation) => $operation === 'create' ? $set('slug', Str::slug((string) $state)) : null),
                    TextInput::make('slug')->required()->maxLength(120)->alphaDash()->unique(ignoreRecord: true),
                    Select::make('category_id')->label('Category')->relationship(name: 'category', titleAttribute: 'name')->searchable(),
                    Select::make('portion_type')->options(collect(PortionType::cases())->mapWithKeys(fn (PortionType $p) => [$p->value => $p->label()])),
                    Textarea::make('description')->rows(3)->columnSpanFull(),
                    FileUpload::make('image_path')->label('Photo')->image()->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])->disk('public')->directory('products')
                        ->imageEditor()->maxSize(5120)->columnSpanFull(),
                    TextInput::make('yield_pct')->label('Yield %')->helperText('For Whole/Half/Quarter variants of the same animal.')
                        ->rule('regex:/^\d{1,3}(\.\d{1,2})?$/')->suffix('%'),
                    Toggle::make('is_active')->label('On sale')->default(true)->inline(false),
                ]),
            Section::make('Catch-weight pricing')
                ->description('Customers pay the actual weight. The card hold is the estimate plus the tolerance.')
                ->columns(3)
                ->schema([
                    TextInput::make('price_per_lb_cents')
                        ->label('Price per lb ($)')
                        ->required()
                        ->rule('regex:/^\$?\d{1,7}(\.\d{1,2})?$/')
                        ->prefix('$')
                        ->formatStateUsing(fn ($state) => is_int($state) ? DollarInput::fromCents($state) : $state)
                        ->dehydrateStateUsing(fn ($state) => DollarInput::toCents((string) $state)),
                    TextInput::make('estimated_weight_lb')
                        ->label('Estimated weight per piece (lb)')
                        ->required()
                        ->rule('regex:/^\d{1,4}(\.\d{1,3})?$/')
                        ->formatStateUsing(fn ($state) => $state instanceof Weight ? $state->toDecimal() : $state)
                        ->dehydrateStateUsing(fn ($state) => Weight::pounds((string) $state)->toDecimal()),
                    TextInput::make('tolerance_pct')
                        ->label('Hold tolerance (%)')
                        ->placeholder((string) config('catchweight.hold_tolerance_pct'))
                        ->helperText('Leave empty to use the store policy.')
                        ->rule('regex:/^\d{1,2}(\.\d{1,2})?$/')
                        ->suffix('%'),
                ]),
        ]);
    }
}
