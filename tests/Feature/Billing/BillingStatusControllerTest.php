<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Models\CheckoutSession;
use App\Domain\Billing\Models\Subscription;
use App\Enums\BillingProvider;
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
}

