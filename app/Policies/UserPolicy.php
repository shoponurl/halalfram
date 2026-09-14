<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\User;

/**
 * Staff management is Owner-only (staff.manage). Accounts are deactivated, never deleted.
 */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageStaff->value);
    }

    public function view(User $user, User $model): bool
    {
        return $user->can(Permission::ManageStaff->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageStaff->value);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(Permission::ManageStaff->value);
    }

    public function delete(User $user, User $model): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    public function restore(User $user, User $model): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $model): bool
    {
        return false;
    }
}
