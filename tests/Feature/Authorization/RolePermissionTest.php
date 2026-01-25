<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
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
        Permission::create(['name' => 'roles.view', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $user->givePermissionTo('roles.view');

        $this->assertTrue(Gate::forUser($user)->allows('viewAny', Role::class));
    }

    public function test_non_admin_without_permission_cannot_update_users(): void
    {
        Permission::create(['name' => 'users.update', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $target = User::factory()->create();

        $this->assertFalse(Gate::forUser($user)->allows('update', $target));
    }

    public function test_user_with_permission_can_update_users(): void
    {
        Permission::create(['name' => 'users.update', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $target = User::factory()->create();
        $user->givePermissionTo('users.update');

        $this->assertTrue(Gate::forUser($user)->allows('update', $target));
    }

    public function test_admin_bypasses_role_permission_checks(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $permission = Permission::create(['name' => 'permissions.delete', 'guard_name' => 'web']);
        $target = User::factory()->create();

        $this->assertTrue(Gate::forUser($admin)->allows('viewAny', Role::class));
        $this->assertTrue(Gate::forUser($admin)->allows('delete', $permission));
        $this->assertTrue(Gate::forUser($admin)->allows('update', $target));
    }

    public function test_impersonate_gate_requires_permission_for_non_admin(): void
    {
        Permission::create(['name' => 'users.impersonate', 'guard_name' => 'web']);
        $user = User::factory()->create();
        $target = User::factory()->create();

        $this->assertFalse(Gate::forUser($user)->allows('impersonate', $target));

        $user->givePermissionTo('users.impersonate');
        $this->assertTrue(Gate::forUser($user)->allows('impersonate', $target));
    }
}
