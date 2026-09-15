<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\AuditLog;
use App\Models\User;

class AuditLogPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ViewAuditLog->value);
    }

    public function view(User $user, AuditLog $log): bool
    {
        return $user->can(Permission::ViewAuditLog->value);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, AuditLog $log): bool
    {
        return false;
    }

    public function delete(User $user, AuditLog $log): bool
    {
        return false;
    }
}
