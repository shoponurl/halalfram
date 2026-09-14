<?php

declare(strict_types=1);

use App\Enums\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(fn () => $this->seed(RolesAndPermissionsSeeder::class))
    ->in('Feature');

/**
 * A staff member with a role. 2FA is already set up unless $withTwoFactor is false.
 */
function staff(Role $role, bool $withTwoFactor = true, bool $active = true): User
{
    $user = User::factory()->create();
    $user->is_active = $active;
    if ($withTwoFactor) {
        $user->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');   // any valid base32 secret
    }
    $user->save();
    $user->assignRole($role->value);

    // Reload like a real sign-in would, so strict mode sees every column
    return $user->fresh();
}
