<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\EntitlementService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class EntitlementServiceTest extends TestCase
{
    use RefreshDatabase;

    protected EntitlementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(EntitlementService::class);
    }

    #[Test]
    public function it_caches_entitlements()
    {
        $user = User::factory()->create();
        $cacheKey = "entitlements:user:{$user->id}";
        $dummyEntitlements = new \App\Domain\Billing\Services\Entitlements([]);

        Cache::shouldReceive('remember')
            ->once()
            ->with($cacheKey, \Mockery::any(), \Mockery::any())
            ->andReturn($dummyEntitlements);

        $result = $this->service->forUser($user);

        $this->assertSame($dummyEntitlements, $result);
    }

    #[Test]
    public function it_returns_active_entitlements_for_active_subscription()
    {
        $user = User::factory()->create();
        // Setup config for a plan
        Config::set('saas.billing.plans.basic', [
            'name' => 'Basic',
            'entitlements' => ['storage_limit_mb' => 512],
        ]);

        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_key' => 'basic',
            'status' => 'active',
        ]);

        // Verify cache is missed or we just check logic (mock remember to execute closure)
        Cache::shouldReceive('remember')
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $entitlements = $this->service->forUser($user);

        $this->assertEquals(512, $entitlements->storage_limit_mb);
    }

    #[Test]
    public function it_handles_grace_period_for_past_due_subscription()
    {
        $user = User::factory()->create();
        Config::set('saas.billing.grace_period_days', 5);
        Config::set('saas.billing.plans.pro', [
            'name' => 'Pro',
            'entitlements' => ['storage_limit_mb' => 2048],
        ]);

        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_key' => 'pro',
            'status' => 'past_due',
        ]);

        Cache::shouldReceive('remember')
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        // Travel 2 days forward - still in grace period
        $this->travel(2)->days();

        $entitlements = $this->service->forUser($user);
        $this->assertEquals(2048, $entitlements->storage_limit_mb);

        // Travel 6 days forward (total) - expired
        $this->travel(4)->days(); // 2+4 = 6

        // Clear cache manually (conceptually, though we mocked remember to execute always)

        $entitlements = $this->service->forUser($user);

        // Should fallback
        $this->assertNull($entitlements->storage_limit_mb);
    }
}
