<?php

namespace App\Support\Authorization;

use App\Enums\PermissionName;
use App\Enums\SystemRoleName;
use App\Models\User;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class PermissionGuardrails
{
    public const DEFAULT_GUARD = 'web';

    public const ADMIN_PANEL_ID = 'admin';

    public const ADMIN_PANEL_PATH = 'admin';

    public const ADMIN_DASHBOARD_ROUTE = 'filament.admin.pages.dashboard';

    /**
     * @return array<int, string>
     */
    public static function corePermissionNames(): array
    {
        return PermissionName::values();
    }

    /**
     * @return array<int, string>
     */
    public static function protectedRoleNames(): array
    {
        return SystemRoleName::values();
    }

    /**
     * @return array<int, string>
     */
    public static function protectedPermissionNames(): array
    {
        return self::corePermissionNames();
    }

    public static function guardName(): string
    {
        return (string) config('auth.defaults.guard', self::DEFAULT_GUARD);
    }

    public static function protectedRoleRenameMessage(): string
    {
        return 'The admin role is protected and cannot be renamed.';
    }

    public static function protectedRoleDeleteMessage(): string
    {
        return 'The admin role is protected and cannot be deleted.';
    }

    public static function protectedPermissionRenameMessage(): string
    {
        return 'This permission is protected and cannot be renamed.';
    }

    public static function protectedPermissionDeleteMessage(): string
    {
        return 'This permission is protected and cannot be deleted.';
    }

    public static function lastAdminDemotionMessage(): string
    {
        return 'At least one admin user must remain.';
    }

    public static function lastAdminDeleteMessage(): string
    {
        return 'The last admin user cannot be deleted.';
    }

    public static function isProtectedRoleName(string $name): bool
    {
        return in_array($name, self::protectedRoleNames(), true);
    }

    public static function isProtectedRole(Role|string|null $role): bool
    {
        if ($role instanceof Role) {
            return self::isProtectedRoleName((string) $role->name);
        }

        if (is_string($role)) {
            return self::isProtectedRoleName($role);
        }

        return false;
    }

    public static function isProtectedPermissionName(string $name): bool
    {
        return in_array($name, self::protectedPermissionNames(), true);
    }

    public static function isProtectedPermission(Permission|string|null $permission): bool
    {
        if ($permission instanceof Permission) {
            return self::isProtectedPermissionName((string) $permission->name);
        }

        if (is_string($permission)) {
            return self::isProtectedPermissionName($permission);
        }

        return false;
    }

    public static function isLastAdminUser(?User $user): bool
    {
        if (! $user || ! $user->exists || ! $user->is_admin) {
            return false;
        }

        return ! User::query()
            ->where('is_admin', true)
            ->whereKeyNot($user->getKey())
            ->exists();
    }

    public static function wouldDemoteLastAdmin(?User $user, bool $nextIsAdmin): bool
    {
        if (! $user || ! $user->exists || ! $user->is_admin || $nextIsAdmin) {
            return false;
        }

        return self::isLastAdminUser($user);
    }
}
