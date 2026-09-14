<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Role;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Spatie\Permission\Traits\HasRoles;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 */
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use InteractsWithAppAuthentication;
    use InteractsWithAppAuthenticationRecovery;
    use Notifiable;

    /**
     * Roles, is_active and 2FA secrets are deliberately NOT fillable — they are set explicitly.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /** @var array<string, mixed> */
    protected $attributes = [
        'is_active' => true,
    ];

    /** @var list<string> */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    /** Only active users with a staff role may enter the admin panel. */
    public function canAccessPanel(Panel $panel): bool
    {
        return $this->is_active
            && $this->hasAnyRole(Role::values())
            && $this->can(Permission::AccessAdmin->value);
    }

    public function isOwner(): bool
    {
        return $this->hasRole(Role::Owner->value);
    }
}
