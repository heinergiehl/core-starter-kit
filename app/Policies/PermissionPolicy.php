<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;
use App\Support\Authorization\PermissionGuardrails;
use Spatie\Permission\Models\Permission;

class PermissionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::PermissionsView->value);
    }

    public function view(User $user, Permission $permission): bool
    {
        return $user->can(PermissionName::PermissionsView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::PermissionsCreate->value);
    }

    public function update(User $user, Permission $permission): bool
    {
        if (PermissionGuardrails::isProtectedPermission($permission)) {
            return false;
        }

        return $user->can(PermissionName::PermissionsUpdate->value);
    }

    public function delete(User $user, Permission $permission): bool
    {
        if (PermissionGuardrails::isProtectedPermission($permission)) {
            return false;
        }

        return $user->can(PermissionName::PermissionsDelete->value);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(PermissionName::PermissionsDelete->value);
    }
}
