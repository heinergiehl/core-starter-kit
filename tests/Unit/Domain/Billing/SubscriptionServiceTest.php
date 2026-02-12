<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Data\SubscriptionData;
use App\Domain\Billing\Exceptions\BillingException;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\BillingPlanService;
use App\Domain\Billing\Services\BillingProviderManager;
use App\Domain\Billing\Services\SubscriptionService;
use App\Enums\BillingProvider;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_normalization_defaults_to_usd_if_missing(): void
    {
        // We use Reflection to test private method or just test dispatchEvents via syncFromProvider logic
        // Easier to test syncFromProvider triggering an event with correct currency

        $user = User::factory()->create();

        $providerManager = $this->mock(BillingProviderManager::class);
        $planService = $this->mock(BillingPlanService::class);

        $service = new SubscriptionService($providerManager, $planService);

        // We can expose the private method via reflection for unit testing precision
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('resolveSubscriptionCurrency');
        $method->setAccessible(true);

        $currency = $method->invokeArgs($service, [['foo' => 'bar']]);

        $this->assertEquals('USD', $currency);
    }

    public function test_normalization_finds_currency_in_metadata(): void
    {
        $providerManager = $this->mock(BillingProviderManager::class);
        $planService = $this->mock(BillingPlanService::class);
        $service = new SubscriptionService($providerManager, $planService);

        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('resolveSubscriptionCurrency');
        $method->setAccessible(true);

        $this->assertEquals('EUR', $method->invokeArgs($service, [['currency' => 'EUR']]));
        $this->assertEquals('GBP', $method->invokeArgs($service, [['items' => ['data' => [['price' => ['currency' => 'GBP']]]]]]));
    }

    public function test_sync_from_provider_keeps_pending_metadata_until_target_is_confirmed(): void
    {
        $user = User::factory()->create();

        Subscription::factory()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe,
            'provider_id' => 'sub_sync_pending_keep',
            'status' => SubscriptionStatus::Active,
            'plan_key' => 'starter',
            'metadata' => [
                'stripe_price_id' => 'price_starter_monthly',
                'pending_plan_key' => 'pro',
                'pending_price_key' => 'monthly',
                'pending_provider_price_id' => 'price_pro_monthly',
            ],
        ]);

        $providerManager = $this->mock(BillingProviderManager::class);
        $planService = $this->mock(BillingPlanService::class);
        $service = new SubscriptionService($providerManager, $planService);

        $service->syncFromProvider(SubscriptionData::fromProvider(
            provider: BillingProvider::Stripe->value,
            providerId: 'sub_sync_pending_keep',
            userId: $user->id,
            planKey: 'starter',
            status: SubscriptionStatus::Active->value,
            quantity: 1,
            dates: [],
            metadata: [
                'stripe_price_id' => 'price_starter_monthly',
                'items' => ['data' => [['price' => ['id' => 'price_starter_monthly']]]],
            ],
        ));

        $subscription = Subscription::query()
            ->where('provider_id', 'sub_sync_pending_keep')
            ->firstOrFail();

        $this->assertSame('starter', $subscription->plan_key);
        $this->assertSame('pro', data_get($subscription->metadata, 'pending_plan_key'));
        $this->assertSame('price_pro_monthly', data_get($subscription->metadata, 'pending_provider_price_id'));
    }

    public function test_sync_from_provider_clears_pending_metadata_when_target_is_confirmed(): void
    {
        $user = User::factory()->create();

        Subscription::factory()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe,
            'provider_id' => 'sub_sync_pending_clear',
            'status' => SubscriptionStatus::Active,
            'plan_key' => 'starter',
            'metadata' => [
                'stripe_price_id' => 'price_starter_monthly',
                'pending_plan_key' => 'pro',
                'pending_price_key' => 'monthly',
                'pending_provider_price_id' => 'price_pro_monthly',
                'pending_plan_change_requested_at' => now()->subMinute()->toIso8601String(),
            ],
        ]);

        $providerManager = $this->mock(BillingProviderManager::class);
        $planService = $this->mock(BillingPlanService::class);
        $service = new SubscriptionService($providerManager, $planService);

        $service->syncFromProvider(SubscriptionData::fromProvider(
            provider: BillingProvider::Stripe->value,
            providerId: 'sub_sync_pending_clear',
            userId: $user->id,
            planKey: 'pro',
            status: SubscriptionStatus::Active->value,
            quantity: 1,
            dates: [],
            metadata: [
                'stripe_price_id' => 'price_pro_monthly',
                'items' => ['data' => [['price' => ['id' => 'price_pro_monthly']]]],
            ],
        ));

        $subscription = Subscription::query()
            ->where('provider_id', 'sub_sync_pending_clear')
            ->firstOrFail();

        $this->assertSame('pro', $subscription->plan_key);
        $this->assertNull(data_get($subscription->metadata, 'pending_plan_key'));
        $this->assertNull(data_get($subscription->metadata, 'pending_provider_price_id'));
    }

    public function test_cancel_rejects_while_plan_change_is_pending(): void
    {
        $user = User::factory()->create();

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe,
            'provider_id' => 'sub_cancel_pending_blocked',
            'status' => SubscriptionStatus::Active,
            'plan_key' => 'starter',
            'metadata' => [
                'pending_plan_key' => 'pro',
                'pending_provider_price_id' => 'price_pro_monthly',
            ],
        ]);

        $providerManager = $this->mock(BillingProviderManager::class);
        $providerManager->shouldNotReceive('adapter');

        $planService = $this->mock(BillingPlanService::class);
        $service = new SubscriptionService($providerManager, $planService);

        try {
            $service->cancel($subscription);
            $this->fail('Expected BillingException was not thrown.');
        } catch (BillingException $exception) {
            $this->assertSame('BILLING_PLAN_CHANGE_ALREADY_PENDING', $exception->getErrorCode());
        }
    }
}
