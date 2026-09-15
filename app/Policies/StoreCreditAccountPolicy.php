<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\StoreCreditAccount;
use App\Models\User;

class StoreCreditAccountPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function view(User $user, StoreCreditAccount $storeCreditAccount): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    /** Accounts are created implicitly by issuing credit — never a blank form. */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, StoreCreditAccount $storeCreditAccount): bool
    {
        return false;
    }

    public function delete(User $user, StoreCreditAccount $storeCreditAccount): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }

    /** Issuing credit goes through App\Actions\Payments\IssueStoreCredit, never a direct balance edit. */
    public function issueCredit(User $user): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }
}
