<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Models\Subscription;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SubscriptionTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
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

    #[Test]
    public function it_clears_entitlement_cache_on_save()
    {
        $user = User::factory()->create();
        $cacheKey = "entitlements:user:{$user->id}";

        Cache::shouldReceive('remember')
            ->andReturn(null);

        // Expectation: called on create AND called on update = 2 times
        Cache::shouldReceive('forget')
            ->times(2)
            ->with($cacheKey);

        $subscription = Subscription::factory()->create(['user_id' => $user->id]);

        // Update to trigger save
        $subscription->update(['status' => 'canceled']);
    }

    #[Test]
    public function it_clears_entitlement_cache_on_delete()
    {
        $user = User::factory()->create();
        $cacheKey = "entitlements:user:{$user->id}";

        $subscription = Subscription::factory()->create(['user_id' => $user->id]);

        Cache::shouldReceive('forget')
            ->once()
            ->with($cacheKey);

        $subscription->delete();
    }
}
