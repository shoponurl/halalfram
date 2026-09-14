<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\CutOption;
use App\Models\User;

class CutOptionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function view(User $user, CutOption $cutOption): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function update(User $user, CutOption $cutOption): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    /** Options on past orders are snapshotted onto the order_item; the row itself is switched off, not deleted. */
    public function delete(User $user, CutOption $cutOption): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
