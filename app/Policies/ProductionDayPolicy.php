<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\ProductionDay;
use App\Models\User;

class ProductionDayPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function view(User $user, ProductionDay $productionDay): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function update(User $user, ProductionDay $productionDay): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function delete(User $user, ProductionDay $productionDay): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
