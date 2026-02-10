<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Data\Plan;
use App\Domain\Billing\Data\Price;
use App\Domain\Billing\Exceptions\BillingException;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\BillingPlanService;
use App\Domain\Billing\Services\BillingProviderManager;
use App\Domain\Billing\Services\SubscriptionService;
use App\Enums\BillingProvider;
use App\Enums\PriceType;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SubscriptionServicePlanChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_change_plan_rejects_one_time_target(): void
    {
        $user = User::factory()->create();

        Subscription::factory()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe,
            'provider_id' => 'sub_plan_change_one_time',
            'status' => SubscriptionStatus::Active,
            'plan_key' => 'pro',
        ]);

        $providerManager = $this->mock(BillingProviderManager::class);
        $planService = $this->mock(BillingPlanService::class, function ($mock): void {
            $mock->shouldReceive('plan')
                ->once()
                ->with('lifetime')
                ->andReturn($this->buildPlan('lifetime', PriceType::OneTime, 'once', PriceType::OneTime));
        });

        $service = new SubscriptionService($providerManager, $planService);

        try {
            $service->changePlan($user, 'lifetime', 'once');
            $this->fail('Expected BillingException was not thrown.');
        } catch (BillingException $exception) {
            $this->assertSame('BILLING_PLAN_CHANGE_INVALID_TARGET', $exception->getErrorCode());
        }
    }

    public function test_change_plan_rejects_noop_when_price_is_already_active(): void
    {
        $user = User::factory()->create();

        Subscription::factory()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe,
            'provider_id' => 'sub_plan_change_same_price',
            'status' => SubscriptionStatus::Active,
            'plan_key' => 'pro',
            'metadata' => [
                'stripe_price_id' => 'price_pro_monthly',
            ],
        ]);

        $providerManager = $this->mock(BillingProviderManager::class);
        $providerManager->shouldNotReceive('adapter');

        $planService = $this->mock(BillingPlanService::class, function ($mock): void {
            $mock->shouldReceive('plan')
                ->once()
                ->with('pro')
                ->andReturn($this->buildPlan('pro', PriceType::Recurring, 'monthly', PriceType::Recurring));

            $mock->shouldReceive('providerPriceId')
                ->once()
                ->with(BillingProvider::Stripe->value, 'pro', 'monthly')
                ->andReturn('price_pro_monthly');
        });

        $service = new SubscriptionService($providerManager, $planService);

        try {
            $service->changePlan($user, 'pro', 'monthly');
            $this->fail('Expected BillingException was not thrown.');
        } catch (BillingException $exception) {
            $this->assertSame('BILLING_PLAN_ALREADY_ACTIVE', $exception->getErrorCode());
        }
    }

    private function buildPlan(
        string $planKey,
        PriceType $planType,
        string $priceKey,
        PriceType $priceType
    ): Plan {
        return new Plan(
            key: $planKey,
            name: ucfirst($planKey),
            summary: '',
            type: $planType,
            highlight: false,
            features: [],
            entitlements: [],
            prices: [
                $priceKey => new Price(
                    key: $priceKey,
                    label: ucfirst($priceKey),
                    amount: 1000,
                    currency: 'USD',
                    interval: $priceKey === 'once' ? 'once' : 'month',
                    intervalCount: 1,
                    type: $priceType,
                    hasTrial: false,
                    trialInterval: null,
                    trialIntervalCount: null,
                    providerIds: [BillingProvider::Stripe->value => 'price_pro_monthly'],
                    providerAmounts: [],
                    providerCurrencies: [],
                    amountIsMinor: true,
                ),
            ],
        );
    }
}

