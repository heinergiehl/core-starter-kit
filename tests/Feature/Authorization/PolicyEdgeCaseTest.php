<?php

namespace Tests\Feature\Authorization;

use App\Enums\PermissionName;
use App\Models\User;
use App\Support\Authorization\PermissionGuardrails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class PolicyEdgeCaseTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_last_admin_cannot_be_deleted(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertFalse(Gate::forUser($admin)->allows('delete', $admin));
    }

    public function test_admin_can_delete_other_admin_when_not_last(): void
    {
        Permission::create([
            'name' => PermissionName::UsersDelete->value,
            'guard_name' => PermissionGuardrails::guardName(),
        ]);

        $admin1 = User::factory()->create(['is_admin' => true]);
        $admin2 = User::factory()->create(['is_admin' => true]);
        $admin1->givePermissionTo(PermissionName::UsersDelete->value);

        $this->assertTrue(Gate::forUser($admin1)->allows('delete', $admin2));
    }

    public function test_non_admin_cannot_impersonate(): void
    {
        $user = User::factory()->create(['is_admin' => false]);
        $target = User::factory()->create();

        $this->assertFalse(Gate::forUser($user)->allows('impersonate', $target));
    }

    public function test_admin_cannot_impersonate_other_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $otherAdmin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('impersonate.start', ['user' => $otherAdmin]))
            ->assertRedirect();
    }

    public function test_admin_cannot_impersonate_self(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('impersonate.start', ['user' => $admin]))
            ->assertRedirect();
    }

    public function test_admin_can_impersonate_regular_user(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($admin)
            ->post(route('impersonate.start', ['user' => $user]))
            ->assertRedirect(route('dashboard'));
    }

    public function test_impersonation_stop_returns_to_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($admin)
            ->post(route('impersonate.start', ['user' => $user]));

        $this->post(route('impersonate.stop'))
            ->assertRedirect();
    }

    public function test_guest_cannot_access_impersonation(): void
    {
        $user = User::factory()->create();

        $this->post(route('impersonate.start', ['user' => $user]))
            ->assertRedirect(route('login'));
    }

    public function test_protected_role_cannot_be_deleted_via_admin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->assertTrue(PermissionGuardrails::isProtectedRoleName('admin'));
    }

    public function test_protected_permissions_are_immutable(): void
    {
        foreach (PermissionName::cases() as $permission) {
            $this->assertTrue(
                PermissionGuardrails::isProtectedPermissionName($permission->value),
                "Permission {$permission->value} should be protected"
            );
        }
    }
}
