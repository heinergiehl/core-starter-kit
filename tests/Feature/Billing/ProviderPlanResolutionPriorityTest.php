<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Adapters\Paddle\Handlers\PaddleSubscriptionHandler;
use App\Domain\Billing\Adapters\Stripe\Handlers\StripeSubscriptionHandler;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\PriceProviderMapping;
use App\Domain\Billing\Models\Product;
use App\Domain\Billing\Models\Subscription;
use App\Enums\BillingProvider;
use App\Enums\PriceType;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderPlanResolutionPriorityTest extends TestCase
{
    use RefreshDatabase;

    public function test_stripe_sync_uses_price_mapping_over_stale_metadata_plan_key(): void
    {
        $starter = Product::factory()->create([
            'key' => 'starter',
            'name' => 'Starter',
            'type' => PriceType::Recurring,
            'is_active' => true,
        ]);
        $pro = Product::factory()->create([
            'key' => 'pro',
            'name' => 'Pro',
            'type' => PriceType::Recurring,
            'is_active' => true,
        ]);

        $starterPrice = Price::factory()->create([
            'product_id' => $starter->id,
            'key' => 'monthly',
            'interval' => 'month',
            'type' => PriceType::Recurring,
            'amount' => 2900,
            'currency' => 'USD',
        ]);
        $proPrice = Price::factory()->create([
            'product_id' => $pro->id,
            'key' => 'monthly',
            'interval' => 'month',
            'type' => PriceType::Recurring,
            'amount' => 5900,
            'currency' => 'USD',
        ]);

        PriceProviderMapping::query()->create([
            'price_id' => $starterPrice->id,
            'provider' => BillingProvider::Stripe->value,
            'provider_id' => 'price_starter_monthly',
        ]);
        PriceProviderMapping::query()->create([
            'price_id' => $proPrice->id,
            'provider' => BillingProvider::Stripe->value,
            'provider_id' => 'price_pro_monthly',
        ]);

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        Subscription::factory()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe,
            'provider_id' => 'sub_stripe_resolution_priority',
            'plan_key' => 'starter',
            'status' => SubscriptionStatus::Active,
            'metadata' => [
                'stripe_price_id' => 'price_starter_monthly',
            ],
        ]);

        $handler = app(StripeSubscriptionHandler::class);
        $handler->syncSubscription([
            'id' => 'sub_stripe_resolution_priority',
            'customer' => 'cus_resolution_priority',
            'status' => SubscriptionStatus::Active->value,
            'items' => [
                'data' => [
                    [
                        'id' => 'si_resolution_priority',
                        'quantity' => 1,
                        'price' => [
                            'id' => 'price_pro_monthly',
                        ],
                    ],
                ],
            ],
            'metadata' => [
                'user_id' => (string) $user->id,
                'plan_key' => 'starter',
            ],
        ], 'customer.subscription.updated');

        $subscription = Subscription::query()
            ->where('provider', BillingProvider::Stripe->value)
            ->where('provider_id', 'sub_stripe_resolution_priority')
            ->firstOrFail();

        $this->assertSame('pro', $subscription->plan_key);
    }

    public function test_paddle_sync_uses_price_mapping_over_stale_custom_data_plan_key(): void
    {
        $starter = Product::factory()->create([
            'key' => 'starter',
            'name' => 'Starter',
            'type' => PriceType::Recurring,
            'is_active' => true,
        ]);
        $pro = Product::factory()->create([
            'key' => 'pro',
            'name' => 'Pro',
            'type' => PriceType::Recurring,
            'is_active' => true,
        ]);

        $starterPrice = Price::factory()->create([
            'product_id' => $starter->id,
            'key' => 'monthly',
            'interval' => 'month',
            'type' => PriceType::Recurring,
            'amount' => 2900,
            'currency' => 'USD',
        ]);
        $proPrice = Price::factory()->create([
            'product_id' => $pro->id,
            'key' => 'monthly',
            'interval' => 'month',
            'type' => PriceType::Recurring,
            'amount' => 5900,
            'currency' => 'USD',
        ]);

        PriceProviderMapping::query()->create([
            'price_id' => $starterPrice->id,
            'provider' => BillingProvider::Paddle->value,
            'provider_id' => 'pri_starter_monthly',
        ]);
        PriceProviderMapping::query()->create([
            'price_id' => $proPrice->id,
            'provider' => BillingProvider::Paddle->value,
            'provider_id' => 'pri_pro_monthly',
        ]);

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        Subscription::factory()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Paddle,
            'provider_id' => 'sub_paddle_resolution_priority',
            'plan_key' => 'starter',
            'status' => SubscriptionStatus::Active,
            'metadata' => [],
        ]);

        $handler = app(PaddleSubscriptionHandler::class);
        $handler->syncSubscription([
            'id' => 'sub_paddle_resolution_priority',
            'status' => SubscriptionStatus::Active->value,
            'items' => [
                [
                    'quantity' => 1,
                    'price' => [
                        'id' => 'pri_pro_monthly',
                    ],
                ],
            ],
            'custom_data' => [
                'user_id' => $user->id,
                'plan_key' => 'starter',
            ],
        ]);

        $subscription = Subscription::query()
            ->where('provider', BillingProvider::Paddle->value)
            ->where('provider_id', 'sub_paddle_resolution_priority')
            ->firstOrFail();

        $this->assertSame('pro', $subscription->plan_key);
    }
}
