<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Models\CheckoutSession;
use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\CheckoutService;
use App\Enums\BillingProvider;
use App\Enums\CheckoutStatus;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingStatusControllerTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_processing_for_checkout_session_without_new_records(): void
    {
        $user = User::factory()->create();

        Subscription::factory()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe,
            'provider_id' => 'sub_old_status',
            'plan_key' => 'legacy',
            'status' => SubscriptionStatus::Canceled,
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $checkoutSession = CheckoutSession::query()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe->value,
            'plan_key' => 'pro',
            'price_key' => 'monthly',
            'quantity' => 1,
            'expires_at' => now()->addHour(),
        ]);

        $response = $this->actingAs($user)->getJson(route('billing.status', [
            'session' => $checkoutSession->uuid,
        ]));

        $response
            ->assertStatus(202)
            ->assertJson([
                'type' => 'checkout',
                'status' => 'processing',
            ]);
    }

    public function test_it_returns_subscription_state_for_matching_checkout_session(): void
    {
        $user = User::factory()->create();

        $checkoutSession = CheckoutSession::query()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe->value,
            'plan_key' => 'pro',
            'price_key' => 'monthly',
            'quantity' => 1,
            'expires_at' => now()->addHour(),
        ]);

        Subscription::factory()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe,
            'provider_id' => 'sub_new_status',
            'plan_key' => 'pro',
            'status' => SubscriptionStatus::Active,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $response = $this->actingAs($user)->getJson(route('billing.status', [
            'session' => $checkoutSession->uuid,
        ]));

        $response
            ->assertOk()
            ->assertJson([
                'type' => 'subscription',
                'status' => SubscriptionStatus::Active->value,
                'plan_key' => 'pro',
            ]);
    }

    public function test_guest_can_poll_signed_checkout_status_after_successful_payment(): void
    {
        $user = User::factory()->create();

        $checkoutSession = CheckoutSession::query()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe->value,
            'provider_session_id' => 'cs_guest_status',
            'plan_key' => 'pro',
            'price_key' => 'monthly',
            'quantity' => 1,
            'expires_at' => now()->addHour(),
        ]);

        Order::query()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe,
            'provider_id' => 'pi_guest_status',
            'plan_key' => 'pro',
            'status' => OrderStatus::Paid,
            'amount' => 9900,
            'currency' => 'USD',
            'paid_at' => now(),
            'metadata' => [
                'session_id' => 'cs_guest_status',
            ],
        ]);

        $urls = app(CheckoutService::class)->buildCheckoutUrls('stripe', $checkoutSession);
        parse_str((string) parse_url($urls['success'], PHP_URL_QUERY), $query);

        $response = $this->getJson(route('billing.status', [
            'session' => $checkoutSession->uuid,
            'sig' => $query['sig'] ?? null,
        ]));

        $response
            ->assertOk()
            ->assertJson([
                'type' => 'order',
                'status' => OrderStatus::Paid->value,
                'plan_key' => 'pro',
            ]);

        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('checkout_sessions', [
            'id' => $checkoutSession->id,
            'status' => CheckoutStatus::Completed->value,
        ]);
    }
}
