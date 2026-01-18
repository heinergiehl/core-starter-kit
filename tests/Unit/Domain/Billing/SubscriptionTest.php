<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Models\Subscription;
use App\Domain\Organization\Models\Team;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_identifies_subscription_on_trial()
    {
        $subscription = Subscription::factory()->create([
            'trial_ends_at' => now()->addDays(5),
            'status' => 'trialing',
        ]);

        $this->assertTrue($subscription->onTrial());

        $expiredTrial = Subscription::factory()->create([
            'trial_ends_at' => now()->subDay(),
            'status' => 'active',
        ]);

        $this->assertFalse($expiredTrial->onTrial());
    }

    /** @test */
    public function it_clears_entitlement_cache_on_save()
    {
        $team = Team::factory()->create();
        $cacheKey = "entitlements:team:{$team->id}";

        // Expectation: called on create AND called on update = 2 times
        Cache::shouldReceive('forget')
            ->times(2)
            ->with($cacheKey);

        $subscription = Subscription::factory()->create(['team_id' => $team->id]);
        
        // Update to trigger save
        $subscription->update(['status' => 'active']);
    }

    /** @test */
    public function it_clears_entitlement_cache_on_delete()
    {
        $team = Team::factory()->create();
        $cacheKey = "entitlements:team:{$team->id}";
        
        $subscription = Subscription::factory()->create(['team_id' => $team->id]);

        Cache::shouldReceive('forget')
            ->once()
            ->with($cacheKey);

        $subscription->delete();
    }
}
