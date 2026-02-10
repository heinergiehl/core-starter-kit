<?php

namespace Tests\Feature\Authorization;

use App\Enums\PermissionName;
use App\Enums\SystemRoleName;
use App\Models\User;
use App\Support\Authorization\PermissionGuardrails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class RolePermissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_non_admin_without_permission_cannot_view_roles(): void
    {
        $user = User::factory()->create();

        $this->assertFalse(Gate::forUser($user)->allows('viewAny', Role::class));
    }

    public function test_user_with_permission_can_view_roles(): void
    {
        Permission::create([
            'name' => PermissionName::RolesView->value,
            'guard_name' => PermissionGuardrails::guardName(),
        ]);
        $user = User::factory()->create();
        $user->givePermissionTo(PermissionName::RolesView->value);

        $this->assertTrue(Gate::forUser($user)->allows('viewAny', Role::class));
    }

    public function test_non_admin_without_permission_cannot_update_users(): void
    {
        Permission::create([
            'name' => PermissionName::UsersUpdate->value,
            'guard_name' => PermissionGuardrails::guardName(),
        ]);
        $user = User::factory()->create();
        $target = User::factory()->create();

        $this->assertFalse(Gate::forUser($user)->allows('update', $target));
    }

    public function test_user_with_permission_can_update_users(): void
    {
        Permission::create([
            'name' => PermissionName::UsersUpdate->value,
            'guard_name' => PermissionGuardrails::guardName(),
        ]);
        $user = User::factory()->create();
        $target = User::factory()->create();
        $user->givePermissionTo(PermissionName::UsersUpdate->value);

        $this->assertTrue(Gate::forUser($user)->allows('update', $target));
    }

    public function test_admin_bypasses_role_permission_checks(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $permission = Permission::create([
            'name' => 'billing.manage',
            'guard_name' => PermissionGuardrails::guardName(),
        ]);
        $target = User::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', Role::class));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $permission));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $target));
    }

    public function test_admin_cannot_delete_protected_system_permission(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $permission = Permission::create([
            'name' => PermissionName::AccessAdminPanel->value,
            'guard_name' => PermissionGuardrails::guardName(),
        ]);

        $this->assertFalse(Gate::forUser($admin)->allows('delete', $permission));
    }

    public function test_last_admin_cannot_be_demoted(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->expectException(ValidationException::class);

        $admin->forceFill(['is_admin' => false])->save();
    }

    public function test_last_admin_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->expectException(ValidationException::class);

        $admin->delete();
    }

    public function test_protected_role_cannot_be_renamed_or_deleted(): void
    {
        $role = Role::create([
            'name' => SystemRoleName::Admin->value,
            'guard_name' => PermissionGuardrails::guardName(),
        ]);

        try {
            $role->update(['name' => 'ops-admin']);
            $this->fail('Expected protected role rename to throw ValidationException.');
        } catch (ValidationException) {
            $this->assertSame(SystemRoleName::Admin->value, $role->fresh()?->name);
        }

        $this->expectException(ValidationException::class);
        $role->delete();
    }

    public function test_protected_permission_cannot_be_renamed_or_deleted(): void
    {
        $permission = Permission::create([
            'name' => PermissionName::AccessAdminPanel->value,
            'guard_name' => PermissionGuardrails::guardName(),
        ]);

        try {
            $permission->update(['name' => 'custom.permission']);
            $this->fail('Expected protected permission rename to throw ValidationException.');
        } catch (ValidationException) {
            $this->assertSame(PermissionName::AccessAdminPanel->value, $permission->fresh()?->name);
        }

        $this->expectException(ValidationException::class);
        $permission->delete();
    }

    public function test_impersonate_gate_requires_permission_for_non_admin(): void
    {
        Permission::create([
            'name' => PermissionName::UsersImpersonate->value,
            'guard_name' => PermissionGuardrails::guardName(),
        ]);
        $user = User::factory()->create();
        $target = User::factory()->create();

        $this->assertFalse(Gate::forUser($user)->allows('impersonate', $target));

        $user->givePermissionTo(PermissionName::UsersImpersonate->value);
        $this->assertTrue(Gate::forUser($user)->allows('impersonate', $target));
    }
}
