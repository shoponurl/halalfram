<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Animal;
use App\Models\User;

class AnimalPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageInventory->value);
    }

    public function view(User $user, Animal $animal): bool
    {
        return $user->can(Permission::ManageInventory->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageInventory->value);
    }

    public function update(User $user, Animal $animal): bool
    {
        return $user->can(Permission::ManageInventory->value);
    }

    /** The traceability register is permanent — never delete an animal record. */
    public function delete(User $user, Animal $animal): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
