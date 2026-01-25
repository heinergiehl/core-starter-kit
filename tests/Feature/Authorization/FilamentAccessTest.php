<?php

namespace Tests\Feature\Authorization;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FilamentAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_admin_cannot_access_admin_roles_pages(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/roles')->assertForbidden();
        $this->actingAs($user)->get('/admin/roles/create')->assertForbidden();
    }

    public function test_non_admin_cannot_access_admin_permissions_pages(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin/permissions')->assertForbidden();
        $this->actingAs($user)->get('/admin/permissions/create')->assertForbidden();
        $this->actingAs($user)->get('/admin/stats')->assertForbidden();
    }

    public function test_admin_can_access_admin_roles_pages(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/roles')->assertOk();
        $this->actingAs($admin)->get('/admin/roles/create')->assertOk();
    }

    public function test_admin_can_access_admin_permissions_pages(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin/permissions')->assertOk();
        $this->actingAs($admin)->get('/admin/permissions/create')->assertOk();
        $this->actingAs($admin)->get('/admin/stats')->assertOk();
    }
}
