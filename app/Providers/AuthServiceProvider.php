<?php

namespace App\Providers;

use App\Enums\PermissionName;
use App\Models\User;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use App\Support\Authorization\PermissionGuardrails;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Role::class => RolePolicy::class,
        Permission::class => PermissionPolicy::class,
        User::class => UserPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();

        Gate::before(function (User $user, mixed $ability, mixed $arguments): ?bool {
            if (! $user->is_admin) {
                return null;
            }

            $abilityName = is_string($ability) ? $ability : null;
            $record = is_array($arguments) ? ($arguments[0] ?? null) : $arguments;

            if ($abilityName === 'delete') {
                if ($record instanceof User && PermissionGuardrails::isLastAdminUser($record)) {
                    return false;
                }

                if ($record instanceof Role && PermissionGuardrails::isProtectedRole($record)) {
                    return false;
                }

                if ($record instanceof Permission && PermissionGuardrails::isProtectedPermission($record)) {
                    return false;
                }
            }

            return true;
        });

        Gate::define('impersonate', function (User $user, User $target): bool {
            if (! $user->is_admin && ! $user->can(PermissionName::UsersImpersonate->value)) {
                return false;
            }

            if ($user->id === $target->id) {
                return false;
            }

            return ! $target->is_admin;
        });
    }
}
