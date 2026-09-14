<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Category;
use App\Models\User;

class CategoryPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function view(User $user, Category $category): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function update(User $user, Category $category): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    /** Categories are switched off, not deleted — products and options may still reference them. */
    public function delete(User $user, Category $category): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
