<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ShipBlackoutDate;
use App\Models\User;

class ShipBlackoutDatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function view(User $user, ShipBlackoutDate $shipBlackoutDate): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function update(User $user, ShipBlackoutDate $shipBlackoutDate): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function delete(User $user, ShipBlackoutDate $shipBlackoutDate): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }
}
