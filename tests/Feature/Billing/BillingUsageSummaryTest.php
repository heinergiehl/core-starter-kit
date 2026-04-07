<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\UsageRecord;
use App\Enums\PriceType;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingUsageSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_usage_based_subscription_shows_current_cycle_summary(): void
    {
        $product = Product::factory()->create([
            'key' => 'scale',
            'name' => 'Scale',
            'type' => 'subscription',
            'is_active' => true,
        ]);

        Price::factory()->metered()->create([
            'product_id' => $product->id,
            'key' => 'metered_monthly',
            'label' => 'Metered Monthly',
            'interval' => 'month',
            'type' => PriceType::Recurring,
            'amount' => 4900,
            'currency' => 'USD',
            'usage_meter_name' => 'API requests',
            'usage_meter_key' => 'api_requests',
            'usage_unit_label' => 'request',
            'usage_included_units' => 10000,
            'usage_package_size' => 1000,
            'usage_overage_amount' => 500,
            'usage_rounding_mode' => 'up',
        ]);

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_key' => 'scale',
            'status' => SubscriptionStatus::Active,
            'renews_at' => now()->addDays(20),
            'metadata' => [
                'price_key' => 'metered_monthly',
            ],
        ]);

        UsageRecord::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'product_id' => $product->id,
            'price_id' => $product->prices()->value('id'),
            'plan_key' => 'scale',
            'price_key' => 'metered_monthly',
            'meter_key' => 'api_requests',
            'quantity' => 11250,
            'occurred_at' => now()->subDay(),
        ]);

        $response = $this->actingAs($user)->get(route('billing.index'));

        $response
            ->assertOk()
            ->assertSeeText('Current Usage')
            ->assertSeeText('API requests')
            ->assertSeeText('11,250 requests')
            ->assertSeeText('11,250 of 10,000 included requests used')
            ->assertSeeText('USD 10.00')
            ->assertSeeText('USD 5.00 / 1,000 requests');
    }

    public function test_blocking_usage_based_subscription_shows_history_and_limit_warning(): void
    {
        $product = Product::factory()->create([
            'key' => 'scale',
            'name' => 'Scale',
            'type' => 'subscription',
            'is_active' => true,
        ]);

        Price::factory()->metered()->create([
            'product_id' => $product->id,
            'key' => 'metered_monthly',
            'label' => 'Metered Monthly',
            'interval' => 'month',
            'type' => PriceType::Recurring,
            'amount' => 4900,
            'currency' => 'USD',
            'usage_meter_name' => 'Tracked seats',
            'usage_meter_key' => 'tracked_seats',
            'usage_unit_label' => 'seat',
            'usage_included_units' => 25,
            'usage_limit_behavior' => 'block',
            'usage_package_size' => 5,
            'usage_overage_amount' => 1900,
        ]);

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_key' => 'scale',
            'status' => SubscriptionStatus::Active,
            'renews_at' => now()->addDays(20),
            'metadata' => [
                'price_key' => 'metered_monthly',
            ],
        ]);

        UsageRecord::factory()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription->id,
            'product_id' => $product->id,
            'price_id' => $product->prices()->value('id'),
            'plan_key' => 'scale',
            'price_key' => 'metered_monthly',
            'meter_key' => 'tracked_seats',
            'quantity' => 25,
            'occurred_at' => now()->subHours(2),
            'metadata' => [
                'source' => 'api_gateway',
            ],
        ]);

        $response = $this->actingAs($user)->get(route('billing.index'));

        $response
            ->assertOk()
            ->assertSeeText('Blocks at limit')
            ->assertSeeText('Recent usage events')
            ->assertSeeText('API Gateway')
            ->assertSeeText('Included usage is exhausted.');
    }
}
