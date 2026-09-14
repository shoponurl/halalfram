<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Lot;
use App\Models\User;

class LotPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageInventory->value);
    }

    public function view(User $user, Lot $lot): bool
    {
        return $user->can(Permission::ManageInventory->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageInventory->value);
    }

    public function update(User $user, Lot $lot): bool
    {
        return $user->can(Permission::ManageInventory->value);
    }

    public function recordWastage(User $user, Lot $lot): bool
    {
        return $user->can(Permission::ManageInventory->value);
    }

    /** For a recall, a lot is withdrawn (status), never deleted — the ledger must stay intact. */
    public function delete(User $user, Lot $lot): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
