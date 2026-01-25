<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedirectIfSubscribedMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Mock the plan so 'pro' is valid
        config(['saas.billing.plans' => [
            'pro' => [
                'name' => 'Pro',
                'prices' => [
                    'monthly' => [
                        'amount' => 1000,
                        'currency' => 'USD',
                        'type' => 'recurring',
                        'interval' => 'month',
                    ],
                ],
            ],
        ]]);

        // Also ensure plan service resolves it (if it uses config directly)
        // If BillingPlanService loads from file, we might need to mock the service instead.
        // Let's rely on validation error message check primarily, but for the middleware test
        // the middleware runs APPROVED routes.
        // Actually, checkout.start validation happens inside the controller.
        // The middleware runs BEFORE the controller.
        // So the invalid plan error in the CONTROLLER means the middleware PASSED (didnt redirect).
        // Wait, for the 'subscribed_user' test, we EXPECT a redirect.
        // If it hit the controller and errored, the middleware FAILED to redirect.
    }

    public function test_subscribed_user_is_redirected_away_from_checkout()
    {
        $user = User::factory()->create();

        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get(route('checkout.start', ['plan' => 'pro', 'price' => 'monthly']));

        $response->assertRedirect(route('billing.index'));
        $response->assertSessionHas('info');
    }

    public function test_unsubscribed_user_can_access_checkout()
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('checkout.start', ['plan' => 'pro', 'price' => 'monthly']));

        $response->assertOk();
    }

    public function test_subscribed_user_cannot_post_to_billing_checkout()
    {
        $user = User::factory()->create();

        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->post(route('billing.checkout'), [
            'plan' => 'pro',
            'price' => 'monthly',
            'provider' => 'stripe',
        ]);

        $response->assertRedirect(route('billing.index'));
    }
}
