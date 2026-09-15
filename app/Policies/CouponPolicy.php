<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\Coupon;
use App\Models\User;

class CouponPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function view(User $user, Coupon $coupon): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function update(User $user, Coupon $coupon): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    /** A coupon with redemptions is switched off (active=false), not deleted — the ledger stays intact. */
    public function delete(User $user, Coupon $coupon): bool
    {
        return $coupon->redeemed_count === 0 && $user->can(Permission::ManageCatalog->value);
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
