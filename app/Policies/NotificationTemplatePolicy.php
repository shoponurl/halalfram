<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Models\NotificationTemplate;
use App\Models\User;

class NotificationTemplatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function view(User $user, NotificationTemplate $notificationTemplate): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    /** One row per (event, channel) — seeded, never freely created. */
    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, NotificationTemplate $notificationTemplate): bool
    {
        return $user->can(Permission::ManageCatalog->value);
    }

    public function delete(User $user, NotificationTemplate $notificationTemplate): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
