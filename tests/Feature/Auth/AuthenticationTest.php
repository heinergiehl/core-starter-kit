<?php

namespace Tests\Feature\Auth;

use App\Enums\PermissionName;
use App\Models\User;
use App\Support\Authorization\PermissionGuardrails;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_admin_panel_users_are_redirected_to_admin_dashboard_after_login(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::create([
            'name' => PermissionName::AccessAdminPanel->value,
            'guard_name' => PermissionGuardrails::guardName(),
        ]);

        $user = User::factory()->create(['is_admin' => false]);
        $user->givePermissionTo(PermissionName::AccessAdminPanel->value);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route(PermissionGuardrails::ADMIN_DASHBOARD_ROUTE, absolute: false));
    }

    public function test_admin_users_are_redirected_to_admin_dashboard_after_login(): void
    {
        $user = User::factory()->create(['is_admin' => true]);

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(route(PermissionGuardrails::ADMIN_DASHBOARD_ROUTE, absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
