<?php

declare(strict_types=1);

namespace App\Filament\Resources\Coupons\Schemas;

use App\Support\DollarInput;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class CouponForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Coupon')
                ->description('Owner decision (guideline S06): the discount is computed against the final, actual-weight total — never the checkout estimate.')
                ->columns(2)
                ->schema([
                    TextInput::make('code')->required()->maxLength(40)->unique(ignoreRecord: true)
                        ->dehydrateStateUsing(fn ($state) => mb_strtoupper((string) $state))
                        ->formatStateUsing(fn ($state) => mb_strtoupper((string) $state)),
                    Select::make('type')->options(['percent' => 'Percent off', 'fixed' => 'Fixed amount off'])->required()->live(),
                    TextInput::make('value')
                        ->label(fn (Get $get) => $get('type') === 'fixed' ? 'Amount off ($)' : 'Percent off')
                        ->required()
                        ->rule(fn (Get $get) => $get('type') === 'fixed' ? 'regex:/^\$?\d{1,4}(\.\d{1,2})?$/' : 'integer|min:1|max:100')
                        ->prefix(fn (Get $get) => $get('type') === 'fixed' ? '$' : null)
                        ->suffix(fn (Get $get) => $get('type') === 'percent' ? '%' : null)
                        ->formatStateUsing(fn ($state, Get $get) => $get('type') === 'fixed' && is_int($state) ? DollarInput::fromCents($state) : $state)
                        ->dehydrateStateUsing(fn ($state, Get $get) => $get('type') === 'fixed' ? DollarInput::toCents((string) $state) : (int) $state),
                    TextInput::make('min_order_cents')
                        ->label('Minimum order ($, optional)')
                        ->prefix('$')
                        ->rule('nullable|regex:/^\$?\d{1,4}(\.\d{1,2})?$/')
                        ->formatStateUsing(fn ($state) => is_int($state) ? DollarInput::fromCents($state) : $state)
                        ->dehydrateStateUsing(fn ($state) => filled($state) ? DollarInput::toCents((string) $state) : null),
                    TextInput::make('max_redemptions')->label('Max uses (optional)')->numeric()->minValue(1),
                    DateTimePicker::make('starts_at')->label('Starts (optional)'),
                    DateTimePicker::make('expires_at')->label('Expires (optional)'),
                    Toggle::make('active')->default(true)->inline(false),
                ]),
        ]);
    }
}
