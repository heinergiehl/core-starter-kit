<?php

namespace App\Providers;

use App\Models\User;
use App\Policies\PermissionPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
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

        Gate::before(function (User $user): ?bool {
            return $user->is_admin ? true : null;
        });

        Gate::define('impersonate', function (User $user, User $target): bool {
            if (! $user->is_admin && ! $user->can('users.impersonate')) {
                return false;
            }

            if ($user->id === $target->id) {
                return false;
            }

            return ! $target->is_admin;
        });
    }
}
