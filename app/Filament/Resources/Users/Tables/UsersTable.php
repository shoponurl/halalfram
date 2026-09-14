<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users\Tables;

use App\Enums\Role;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('role')
                    ->state(fn (User $record) => Role::tryFrom((string) $record->getRoleNames()->first())?->label())
                    ->badge(),
                IconColumn::make('two_factor')
                    ->label('2FA')
                    ->state(fn (User $record) => filled($record->getAppAuthenticationSecret()))
                    ->boolean(),
                IconColumn::make('is_active')->label('Active')->boolean(),
                TextColumn::make('last_login_at')->label('Last sign-in')->since()->placeholder('Never')->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->options(collect(Role::cases())->mapWithKeys(fn (Role $r) => [$r->value => $r->label()]))
                    ->query(fn ($query, array $data) => filled($data['value']) ? $query->role($data['value']) : $query),
                TernaryFilter::make('is_active')->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
        // No delete: staff are deactivated so orders, weights and audit entries keep pointing at a real person.
    }
}
