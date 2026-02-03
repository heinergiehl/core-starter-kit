<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\SaasStatsService;
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
        $this->service = new SaasStatsService;
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

        $count = $this->service->getCancellationsThisMonth();

        $this->assertEquals(1, $count);
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
        $user = User::factory()->create();

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

        // 2 subscriptions
        Subscription::factory()->count(2)->create([
            'user_id' => $user->id,
            'plan_key' => 'starter',
            'status' => 'active',
            'canceled_at' => null,
        ]);

        $arpu = $this->service->calculateARPU();

        // $29 * 2 subscriptions = $58 MRR / 2 users = $29 ARPU
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
}
