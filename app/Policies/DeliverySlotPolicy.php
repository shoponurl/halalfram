<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\DeliverySlot;
use App\Models\User;

class DeliverySlotPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function view(User $user, DeliverySlot $deliverySlot): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function update(User $user, DeliverySlot $deliverySlot): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function delete(User $user, DeliverySlot $deliverySlot): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
