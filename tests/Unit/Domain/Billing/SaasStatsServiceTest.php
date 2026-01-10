<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\SaasStatsService;
use App\Domain\Organization\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaasStatsServiceTest extends TestCase
{
    use RefreshDatabase;

    private SaasStatsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SaasStatsService();
    }

    public function test_gets_active_subscription_count(): void
    {
        $team = Team::factory()->create();

        // Active subscription
        Subscription::factory()->create([
            'team_id' => $team->id,
            'status' => 'active',
            'canceled_at' => null,
        ]);

        // Trialing subscription
        Subscription::factory()->create([
            'team_id' => $team->id,
            'status' => 'trialing',
            'canceled_at' => null,
        ]);

        // Canceled subscription - should not count
        Subscription::factory()->create([
            'team_id' => $team->id,
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);

        $count = $this->service->getActiveSubscriptionCount();

        $this->assertEquals(2, $count);
    }

    public function test_gets_new_subscriptions_this_month(): void
    {
        $team = Team::factory()->create();

        // This month
        Subscription::factory()->create([
            'team_id' => $team->id,
            'created_at' => now(),
        ]);

        // Last month - should not count
        Subscription::factory()->create([
            'team_id' => $team->id,
            'created_at' => now()->subMonth(),
        ]);

        $count = $this->service->getNewSubscriptionsThisMonth();

        $this->assertEquals(1, $count);
    }

    public function test_gets_cancellations_this_month(): void
    {
        $team = Team::factory()->create();

        // Canceled this month
        Subscription::factory()->create([
            'team_id' => $team->id,
            'canceled_at' => now(),
        ]);

        // Canceled last month - should not count
        Subscription::factory()->create([
            'team_id' => $team->id,
            'canceled_at' => now()->subMonth(),
        ]);

        $count = $this->service->getCancellationsThisMonth();

        $this->assertEquals(1, $count);
    }

    public function test_calculates_churn_rate(): void
    {
        $team = Team::factory()->create();

        // 10 subscriptions created 60 days ago (starting base)
        for ($i = 0; $i < 10; $i++) {
            Subscription::factory()->create([
                'team_id' => $team->id,
                'status' => 'active',
                'created_at' => now()->subDays(60),
                'canceled_at' => null,
            ]);
        }

        // 2 canceled in last 30 days
        for ($i = 0; $i < 2; $i++) {
            Subscription::factory()->create([
                'team_id' => $team->id,
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
        $team = Team::factory()->create();

        $plan = Plan::factory()->create([
            'key' => 'pro-monthly',
        ]);

        Price::factory()->create([
            'plan_id' => $plan->id,
            'amount' => 4900, // $49.00
            'interval' => 'month',
            'interval_count' => 1,
            'is_active' => true,
        ]);

        Subscription::factory()->create([
            'team_id' => $team->id,
            'plan_key' => 'pro-monthly',
            'status' => 'active',
            'canceled_at' => null,
        ]);

        $mrr = $this->service->calculateMRR();

        $this->assertEquals(49.0, $mrr);
    }

    public function test_calculates_arpu(): void
    {
        $team = Team::factory()->create();

        $plan = Plan::factory()->create([
            'key' => 'starter',
        ]);

        Price::factory()->create([
            'plan_id' => $plan->id,
            'amount' => 2900, // $29.00
            'interval' => 'month',
            'interval_count' => 1,
            'is_active' => true,
        ]);

        // 2 subscriptions
        Subscription::factory()->count(2)->create([
            'team_id' => $team->id,
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
