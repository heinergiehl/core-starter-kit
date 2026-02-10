<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;
use App\Support\Authorization\PermissionGuardrails;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::RolesView->value);
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can(PermissionName::RolesView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::RolesCreate->value);
    }

    public function update(User $user, Role $role): bool
    {
        if (PermissionGuardrails::isProtectedRole($role)) {
            return false;
        }

        return $user->can(PermissionName::RolesUpdate->value);
    }

    public function delete(User $user, Role $role): bool
    {
        if (PermissionGuardrails::isProtectedRole($role)) {
            return false;
        }

        return $user->can(PermissionName::RolesDelete->value);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(PermissionName::RolesDelete->value);
    }
}
