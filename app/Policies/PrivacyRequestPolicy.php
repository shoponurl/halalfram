<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\PrivacyRequestStatus;
use App\Models\PrivacyRequest;
use App\Models\User;

class PrivacyRequestPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Permission::ManageCompliance->value);
    }

    public function view(User $user, PrivacyRequest $request): bool
    {
        return $user->can(Permission::ManageCompliance->value);
    }

    public function create(User $user): bool
    {
        return false;   // filed only through the public form
    }

    public function update(User $user, PrivacyRequest $request): bool
    {
        return false;
    }

    public function delete(User $user, PrivacyRequest $request): bool
    {
        return false;
    }

    public function fulfill(User $user, PrivacyRequest $request): bool
    {
        return $user->can(Permission::ManageCompliance->value) && $request->status === PrivacyRequestStatus::Pending;
    }
}
