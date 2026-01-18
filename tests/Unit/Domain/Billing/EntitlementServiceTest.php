<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\EntitlementService;
use App\Domain\Organization\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
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

    /** @test */
    public function it_caches_entitlements()
    {
        $team = Team::factory()->create();
        $cacheKey = "entitlements:team:{$team->id}";
        $dummyEntitlements = new \App\Domain\Billing\Services\Entitlements([]);

        Cache::shouldReceive('remember')
            ->once()
            ->with($cacheKey, \Mockery::any(), \Mockery::any())
            ->andReturn($dummyEntitlements);

        $result = $this->service->forTeam($team);
        
        $this->assertSame($dummyEntitlements, $result);
    }
    
    /** @test */
    public function it_returns_active_entitlements_for_active_subscription()
    {
        $team = Team::factory()->create();
        // Setup config for a plan
        Config::set('saas.billing.plans.basic', [
            'name' => 'Basic',
            'entitlements' => ['max_seats' => 5],
        ]);

        Subscription::factory()->create([
            'team_id' => $team->id,
            'plan_key' => 'basic',
            'status' => 'active',
        ]);

        // Verify cache is missed or we just check logic (mock remember to execute closure)
        Cache::shouldReceive('remember')
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $entitlements = $this->service->forTeam($team);
        
        $this->assertEquals(5, $entitlements->max_seats);
    }

    /** @test */
    public function it_handles_grace_period_for_past_due_subscription()
    {
        $team = Team::factory()->create();
        Config::set('saas.billing.grace_period_days', 5);
        Config::set('saas.billing.plans.pro', [
            'name' => 'Pro',
            'entitlements' => ['max_seats' => 10],
        ]);

        Subscription::factory()->create([
            'team_id' => $team->id,
            'plan_key' => 'pro',
            'status' => 'past_due',
        ]);

        Cache::shouldReceive('remember')
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        // Travel 2 days forward - still in grace period
        $this->travel(2)->days();
        
        $entitlements = $this->service->forTeam($team);
        $this->assertEquals(10, $entitlements->max_seats);

        // Travel 6 days forward (total) - expired
        $this->travel(4)->days(); // 2+4 = 6

        // Clear cache manually (conceptually, though we mocked remember to execute always)
        
        $entitlements = $this->service->forTeam($team);
        
        // Should fallback
        $this->assertNotEquals(10, $entitlements->max_seats);
    }
}
