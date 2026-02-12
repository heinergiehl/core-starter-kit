<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\SaasStatsService;
use App\Enums\OrderStatus;
use App\Enums\PriceType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaasStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    private SaasStatsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SaasStatsService::class);
    }

    public function test_gets_active_subscription_count(): void
    {
        $user = User::factory()->create();

        // Active subscription
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'active',
            'canceled_at' => null,
        ]);

        // Trialing subscription
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'trialing',
            'canceled_at' => null,
        ]);

        // Canceled subscription - should not count
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);

        $count = $this->service->getActiveSubscriptionCount();

        $this->assertEquals(2, $count);
    }

    public function test_gets_new_subscriptions_this_month(): void
    {
        $user = User::factory()->create();

        // This month
        Subscription::factory()->create([
            'user_id' => $user->id,
            'created_at' => now(),
        ]);

        // Last month - should not count
        Subscription::factory()->create([
            'user_id' => $user->id,
            'created_at' => now()->subMonth(),
        ]);

        $count = $this->service->getNewSubscriptionsThisMonth();

        $this->assertEquals(1, $count);
    }

    public function test_gets_cancellations_this_month(): void
    {
        $user = User::factory()->create();

        // Canceled this month
        Subscription::factory()->create([
            'user_id' => $user->id,
            'canceled_at' => now(),
        ]);

        // Canceled last month - should not count
        Subscription::factory()->create([
            'user_id' => $user->id,
            'canceled_at' => now()->subMonth(),
        ]);

        // Some providers may send status/ends_at but no canceled_at
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => 'canceled',
            'canceled_at' => null,
            'ends_at' => now(),
        ]);

        $count = $this->service->getCancellationsThisMonth();

        $this->assertEquals(2, $count);
    }

    public function test_calculates_churn_rate(): void
    {
        $user = User::factory()->create();

        // 10 subscriptions created 60 days ago (starting base)
        for ($i = 0; $i < 8; $i++) {
            Subscription::factory()->create([
                'user_id' => $user->id,
                'status' => 'active',
                'created_at' => now()->subDays(60),
                'canceled_at' => null,
            ]);
        }

        // 2 canceled in last 30 days
        for ($i = 0; $i < 2; $i++) {
            Subscription::factory()->create([
                'user_id' => $user->id,
                'status' => 'canceled',
                'created_at' => now()->subDays(60),
                'canceled_at' => now()->subDays(15),
            ]);
        }

        $churnRate = $this->service->calculateChurnRate();

        // 2 churned out of 10 = 20%
        $this->assertEquals(20.0, $churnRate);
    }

    public function test_calculates_mrr_with_plans(): void
    {
        $user = User::factory()->create();

        $product = \App\Domain\Billing\Models\Product::factory()->create([
            'key' => 'pro-monthly',
        ]);

        Price::factory()->create([
            'product_id' => $product->id,
            'amount' => 4900, // $49.00
            'interval' => 'month',
            'interval_count' => 1,
            'is_active' => true,
        ]);

        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_key' => 'pro-monthly',
            'status' => 'active',
            'canceled_at' => null,
        ]);

        $mrr = $this->service->calculateMRR();

        $this->assertEquals(49.0, $mrr);
    }

    public function test_calculates_arpu(): void
    {
        $userOne = User::factory()->create();
        $userTwo = User::factory()->create();

        $product = \App\Domain\Billing\Models\Product::factory()->create([
            'key' => 'starter',
        ]);

        Price::factory()->create([
            'product_id' => $product->id,
            'amount' => 2900, // $29.00
            'interval' => 'month',
            'interval_count' => 1,
            'is_active' => true,
        ]);

        Subscription::factory()->create([
            'user_id' => $userOne->id,
            'plan_key' => 'starter',
            'status' => 'active',
            'canceled_at' => null,
        ]);
        Subscription::factory()->create([
            'user_id' => $userTwo->id,
            'plan_key' => 'starter',
            'status' => 'active',
            'canceled_at' => null,
        ]);

        $arpu = $this->service->calculateARPU();

        // $29 * 2 subscriptions = $58 MRR / 2 active customers = $29 ARPU
        $this->assertEquals(29.0, $arpu);
    }

    public function test_returns_zero_mrr_with_no_subscriptions(): void
    {
        $mrr = $this->service->calculateMRR();

        $this->assertEquals(0, $mrr);
    }

    public function test_returns_zero_arpu_with_no_subscriptions(): void
    {
        $arpu = $this->service->calculateARPU();

        $this->assertEquals(0, $arpu);
    }

    public function test_plan_distribution_is_empty_when_no_active_subscriptions(): void
    {
        Subscription::factory()->canceled()->create();

        $distribution = $this->service->getPlanDistribution();

        $this->assertSame([], $distribution);
    }

    public function test_metrics_include_one_time_monthly_orders_and_revenue(): void
    {
        $user = User::factory()->create();

        $oneTimeProduct = Product::factory()->create([
            'key' => 'lifetime',
            'type' => PriceType::OneTime,
        ]);

        $recurringProduct = Product::factory()->create([
            'key' => 'pro',
            'type' => PriceType::Recurring,
        ]);

        Order::query()->create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'provider_id' => 'pi_one_time_1',
            'plan_key' => $oneTimeProduct->key,
            'status' => OrderStatus::Paid->value,
            'amount' => 4900,
            'currency' => 'USD',
            'paid_at' => now(),
            'metadata' => [],
        ]);

        // One-time checkout can still target a recurring product key; keep it counted by missing subscription ID.
        Order::query()->create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'provider_id' => 'pi_one_time_2',
            'plan_key' => $recurringProduct->key,
            'status' => OrderStatus::Completed->value,
            'amount' => 1500,
            'currency' => 'USD',
            'paid_at' => now(),
            'metadata' => [],
        ]);

        // Subscription transaction should be excluded.
        Order::query()->create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'provider_id' => 'pi_subscription',
            'plan_key' => $recurringProduct->key,
            'status' => OrderStatus::Paid->value,
            'amount' => 2900,
            'currency' => 'USD',
            'paid_at' => now(),
            'metadata' => [
                'subscription_id' => 'sub_123',
            ],
        ]);

        // Last month should be excluded.
        Order::query()->create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'provider_id' => 'pi_old_one_time',
            'plan_key' => $oneTimeProduct->key,
            'status' => OrderStatus::Paid->value,
            'amount' => 9900,
            'currency' => 'USD',
            'paid_at' => now()->subMonth(),
            'metadata' => [],
        ]);

        $metrics = $this->service->getMetrics();

        $this->assertSame(2, $metrics['one_time_orders_this_month']);
        $this->assertEquals(64.0, $metrics['one_time_revenue_this_month']);
    }
}
