<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\BillingMetricsService;
use App\Enums\BillingProvider;
use App\Enums\PriceType;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingMetricsServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_snapshot_counts_recent_cancellation_when_canceled_at_is_missing(): void
    {
        Subscription::factory()->create([
            'status' => SubscriptionStatus::Canceled,
            'canceled_at' => null,
            'ends_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);

        $metrics = app(BillingMetricsService::class)->snapshot();

        $this->assertSame(1, $metrics['cancellations_last_30_days']);
    }

    public function test_snapshot_calculates_mrr_for_database_backed_plan_dto(): void
    {
        $user = User::factory()->create();

        $product = Product::factory()->create([
            'key' => 'pro-monthly',
            'type' => PriceType::Recurring->value,
            'is_active' => true,
        ]);

        Price::factory()->create([
            'product_id' => $product->id,
            'key' => 'monthly',
            'label' => 'Monthly',
            'amount' => 4900,
            'interval' => 'month',
            'interval_count' => 1,
            'is_active' => true,
        ]);

        Subscription::factory()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe,
            'plan_key' => 'pro-monthly',
            'status' => SubscriptionStatus::Active,
            'canceled_at' => null,
            'metadata' => ['price_key' => 'monthly'],
        ]);

        $metrics = app(BillingMetricsService::class)->snapshot();

        $this->assertSame(49.0, $metrics['mrr']);
    }

    public function test_snapshot_calculates_churn_from_starting_period_subscriptions(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 8; $i++) {
            Subscription::factory()->create([
                'user_id' => $user->id,
                'status' => SubscriptionStatus::Active,
                'created_at' => now()->subDays(60),
                'canceled_at' => null,
            ]);
        }

        for ($i = 0; $i < 2; $i++) {
            Subscription::factory()->create([
                'user_id' => $user->id,
                'status' => SubscriptionStatus::Canceled,
                'created_at' => now()->subDays(60),
                'canceled_at' => now()->subDays(15),
            ]);
        }

        $metrics = app(BillingMetricsService::class)->snapshot();

        $this->assertSame(20.0, round($metrics['churn_rate'], 1));
    }
}
