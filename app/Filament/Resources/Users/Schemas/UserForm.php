<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Role;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        $isSelf = fn (?User $record): bool => $record !== null && $record->is(auth()->user());

        return $schema->components([
            Section::make('Staff member')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->required()->maxLength(120),
                    TextInput::make('email')->email()->required()->maxLength(190)->unique(ignoreRecord: true),
                    Select::make('role')
                        ->options(collect(Role::cases())->mapWithKeys(fn (Role $r) => [$r->value => $r->label()]))
                        ->required()
                        ->native(false)
                        ->disabled($isSelf)
                        ->helperText(fn (?User $record) => $isSelf($record) ? 'You can’t change your own role.' : 'Controls what this person can see and change.'),
                    Toggle::make('is_active')
                        ->label('Can sign in')
                        ->default(true)
                        ->disabled($isSelf)
                        ->inline(false),
                    TextInput::make('password')
                        ->password()
                        ->revealable()
                        ->rule(Password::min(12)->mixedCase()->numbers())
                        ->required(fn (string $operation) => $operation === 'create')
                        ->dehydrated(fn (?string $state) => filled($state))
                        ->helperText(fn (string $operation) => $operation === 'create'
                            ? 'Temporary password — share it privately. They set up 2FA on first sign-in.'
                            : 'Leave empty to keep the current password.'),
                ]),
        ]);
    }
}
