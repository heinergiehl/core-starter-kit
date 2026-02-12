<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Services\BillingDashboardService;
use App\Domain\Billing\Services\BillingPlanService;
use App\Enums\OrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class BillingDashboardServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_handles_catalog_lookup_failures_for_recent_one_time_orders(): void
    {
        $user = User::factory()->create();

        $order = Order::query()->create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'provider_id' => 'pi_recent_'.uniqid(),
            'plan_key' => 'agency',
            'status' => OrderStatus::Paid->value,
            'amount' => 5000,
            'currency' => 'USD',
            'paid_at' => now(),
            'metadata' => [
                'metadata' => [
                    'price_key' => 'once',
                ],
            ],
        ]);

        $planService = Mockery::mock(BillingPlanService::class);
        $planService->shouldReceive('plan')
            ->once()
            ->with('agency')
            ->andThrow(new \ValueError('Invalid catalog data'));

        $service = new BillingDashboardService($planService);
        $data = $service->getData($user);

        $this->assertNotNull($data['recentOneTimeOrder']);
        $this->assertSame($order->id, $data['recentOneTimeOrder']->id);
        $this->assertNull($data['recentOneTimePlanAmount']);
        $this->assertNull($data['recentOneTimePlanCurrency']);
    }
}
