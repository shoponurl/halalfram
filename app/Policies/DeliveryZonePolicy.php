<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\DeliveryZone;
use App\Models\User;

class DeliveryZonePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function view(User $user, DeliveryZone $deliveryZone): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function update(User $user, DeliveryZone $deliveryZone): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    /** A zone with orders/zips referencing it is switched off, not deleted. */
    public function delete(User $user, DeliveryZone $deliveryZone): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
