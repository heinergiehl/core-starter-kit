<?php

namespace App\Policies;

use App\Enums\PermissionName;
use App\Models\User;
use App\Support\Authorization\PermissionGuardrails;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(PermissionName::UsersView->value);
    }

    public function view(User $user, User $model): bool
    {
        return $user->can(PermissionName::UsersView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(PermissionName::UsersCreate->value);
    }

    public function update(User $user, User $model): bool
    {
        return $user->can(PermissionName::UsersUpdate->value);
    }

    public function delete(User $user, User $model): bool
    {
        if (PermissionGuardrails::isLastAdminUser($model)) {
            return false;
        }

        return $user->can(PermissionName::UsersDelete->value);
    }

    public function deleteAny(User $user): bool
    {
        return $user->can(PermissionName::UsersDelete->value);
    }

    public function impersonate(User $user, User $model): bool
    {
        return $user->can(PermissionName::UsersImpersonate->value);
    }
}
