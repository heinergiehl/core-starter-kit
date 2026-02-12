<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Subscription;
use App\Enums\OrderStatus;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RedirectIfSubscribedMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create the 'pro' product and 'monthly' price in the database
        // because BillingPlanService now reads from DB, not config.
        $product = \App\Domain\Billing\Models\Product::factory()->create([
            'key' => 'pro',
            'name' => 'Pro',
            'is_active' => true,
        ]);

        \App\Domain\Billing\Models\Price::factory()->create([
            'product_id' => $product->id,
            'key' => 'monthly',
            'interval' => 'month',
            'amount' => 1000,
            'currency' => 'USD',
            'is_active' => true,
        ]);
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

        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);

        $response = $this->actingAs($user)->post(route('billing.checkout'), [
            'plan' => 'pro',
            'price' => 'monthly',
            'provider' => 'stripe',
        ]);

        $response->assertRedirect(route('billing.index'));
    }

    public function test_user_with_completed_order_can_access_checkout_for_conversion(): void
    {
        $user = User::factory()->create();

        Order::query()->create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'provider_id' => 'pi_test_'.uniqid(),
            'plan_key' => 'pro',
            'status' => OrderStatus::Paid->value,
            'amount' => 1000,
            'currency' => 'USD',
            'paid_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('checkout.start', ['plan' => 'pro', 'price' => 'monthly']));

        $response->assertOk();
    }
}
