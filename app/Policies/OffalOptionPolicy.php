<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\OffalOption;
use App\Models\User;

class OffalOptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function view(User $user, OffalOption $offalOption): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function update(User $user, OffalOption $offalOption): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function delete(User $user, OffalOption $offalOption): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
