<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\PackingRule;
use App\Models\User;

class PackingRulePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function view(User $user, PackingRule $packingRule): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function update(User $user, PackingRule $packingRule): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    /** A rule an order already references is switched off, not deleted. */
    public function delete(User $user, PackingRule $packingRule): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
