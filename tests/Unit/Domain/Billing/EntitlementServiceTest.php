<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\EntitlementService;
use App\Enums\BillingProvider;
use App\Enums\OrderStatus;
use App\Enums\PriceType;
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
        $dummyEntitlements = new \App\Domain\Billing\Data\Entitlements([]);

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

    #[Test]
    public function it_reads_entitlements_from_database_backed_plan_projection(): void
    {
        $user = User::factory()->create();

        $product = \App\Domain\Billing\Models\Product::factory()->create([
            'key' => 'agency',
            'type' => PriceType::Recurring->value,
            'entitlements' => ['storage_limit_mb' => 8192],
            'is_active' => true,
        ]);

        \App\Domain\Billing\Models\Price::factory()->create([
            'product_id' => $product->id,
            'key' => 'monthly',
            'interval' => 'month',
            'interval_count' => 1,
            'amount' => 9900,
            'is_active' => true,
        ]);

        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_key' => 'agency',
            'status' => 'active',
        ]);

        Cache::shouldReceive('remember')
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $entitlements = $this->service->forUser($user);

        $this->assertSame(8192, $entitlements->storage_limit_mb);
    }

    #[Test]
    public function it_prefers_latest_paid_one_time_order_when_subscription_grace_period_has_expired(): void
    {
        $user = User::factory()->create();

        Config::set('saas.billing.grace_period_days', 5);
        Config::set('saas.billing.plans.pro', [
            'name' => 'Pro',
            'entitlements' => ['storage_limit_mb' => 2048],
        ]);
        Config::set('saas.billing.plans.lifetime', [
            'name' => 'Lifetime',
            'entitlements' => ['storage_limit_mb' => 8192],
        ]);

        Order::query()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe,
            'provider_id' => 'pi_one_time_order',
            'plan_key' => 'lifetime',
            'status' => OrderStatus::Paid,
            'amount' => 14999,
            'currency' => 'USD',
            'paid_at' => now()->subDays(7),
            'metadata' => [],
        ]);

        Order::query()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe,
            'provider_id' => 'pi_subscription_invoice',
            'plan_key' => 'pro',
            'status' => OrderStatus::Paid,
            'amount' => 9900,
            'currency' => 'USD',
            'paid_at' => now()->subDay(),
            'metadata' => [
                'subscription_id' => 'sub_123',
            ],
        ]);

        Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_key' => 'pro',
            'status' => 'past_due',
            'updated_at' => now()->subDays(6),
        ]);

        Cache::shouldReceive('remember')
            ->andReturnUsing(fn ($key, $ttl, $callback) => $callback());

        $entitlements = $this->service->forUser($user);

        $this->assertSame(8192, $entitlements->storage_limit_mb);
    }

    #[Test]
    public function it_clears_cached_entitlements_when_a_paid_order_changes(): void
    {
        $user = User::factory()->create();
        $cacheKey = EntitlementService::CACHE_KEY_PREFIX.$user->id;

        Cache::put($cacheKey, ['stale' => true], now()->addMinutes(30));

        Order::query()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe,
            'provider_id' => 'pi_cache_clear',
            'plan_key' => 'lifetime',
            'status' => OrderStatus::Paid,
            'amount' => 14999,
            'currency' => 'USD',
            'paid_at' => now(),
            'metadata' => [],
        ]);

        $this->assertNull(Cache::get($cacheKey));
    }
}
