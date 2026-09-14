<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users;

use App\Enums\Role;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

/**
 * Staff accounts. Access is decided by App\Policies\UserPolicy (Owner only).
 */
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'staff member';

    protected static ?string $pluralModelLabel = 'staff';

    protected static ?string $navigationLabel = 'Staff';

    protected static string|UnitEnum|null $navigationGroup = 'Settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    /** Only people with a staff role are listed here; customer accounts are managed elsewhere. */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereHas('roles', fn (Builder $roles) => $roles->whereIn('name', Role::values()))
            ->with('roles');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
