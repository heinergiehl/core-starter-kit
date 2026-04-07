<?php

namespace Tests\Feature\Auth;

use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RouteProtectionTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, string> */
    private array $authRoutes = [
        'dashboard' => '/dashboard',
        'billing' => '/billing',
        'profile' => '/profile',
        'onboarding' => '/onboarding',
        'two-factor-enable' => '/two-factor/enable',
    ];

    public function test_guest_is_redirected_from_dashboard(): void
    {
        $this->get('/dashboard')->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_from_billing(): void
    {
        $this->get('/billing')->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_from_profile(): void
    {
        $this->get('/profile')->assertRedirect(route('login'));
    }

    public function test_guest_cannot_post_to_roadmap_store(): void
    {
        $this->post(route('roadmap.store'), [
            'title' => 'Test',
            'category' => 'feature',
            'idempotency_key' => fake()->uuid(),
        ])->assertRedirect(route('login'));
    }

    public function test_guest_cannot_post_to_two_factor_enable(): void
    {
        $this->post('/two-factor/enable')->assertRedirect(route('login'));
    }

    public function test_guest_cannot_post_impersonate(): void
    {
        $user = User::factory()->create();

        $this->post(route('impersonate.start', ['user' => $user]))
            ->assertRedirect(route('login'));
    }

    public function test_non_admin_receives_403_on_admin_panel(): void
    {
        $user = User::factory()->create(['is_admin' => false]);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public function test_admin_can_access_admin_panel(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin)->get('/admin')->assertOk();
    }

    public function test_unverified_user_is_redirected_to_verification(): void
    {
        $user = User::factory()->unverified()->create();

        Subscription::factory()->active()->create(['user_id' => $user->id]);

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertRedirect(route('verification.notice'));
    }

    public function test_unsubscribed_user_cannot_access_dashboard(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/dashboard');

        // Should redirect to pricing or similar for unsubscribed users
        $this->assertNotEquals(200, $response->getStatusCode());
    }

    public function test_user_with_active_subscription_can_access_dashboard(): void
    {
        $user = User::factory()->create();
        Subscription::factory()->active()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_user_with_paid_order_can_access_dashboard(): void
    {
        $user = User::factory()->create();
        Order::factory()->paid()->create(['user_id' => $user->id]);

        $this->actingAs($user)->get('/dashboard')->assertOk();
    }

    public function test_user_with_failed_order_cannot_access_dashboard(): void
    {
        $user = User::factory()->create();
        Order::factory()->failed()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get('/dashboard');
        $this->assertNotEquals(200, $response->getStatusCode());
    }

    public function test_nonexistent_route_returns_404(): void
    {
        $this->get('/this-does-not-exist-'.fake()->uuid())->assertNotFound();
    }

    public function test_locale_update_rejects_invalid_locale(): void
    {
        $this->post(route('locale.update'), [
            'locale' => 'xx_invalid',
        ])->assertSessionHasErrors('locale');
    }

    public function test_locale_update_accepts_valid_locale(): void
    {
        $response = $this->post(route('locale.update'), [
            'locale' => 'de',
        ]);

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();
    }
}
