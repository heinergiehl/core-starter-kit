<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Data\Price as BillingPrice;
use App\Domain\Billing\Exceptions\UsageQuotaExceededException;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\UsageMeterService;
use App\Enums\PriceType;
use App\Enums\UsageLimitBehavior;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UsageMeterServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_records_usage_against_a_subscription_context(): void
    {
        $service = app(UsageMeterService::class);
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_key' => 'scale',
            'metadata' => [
                'price_key' => 'metered_monthly',
            ],
        ]);

        $record = $service->record(
            user: $user,
            meterKey: 'api_requests',
            quantity: 25,
            subscription: $subscription,
            metadata: ['source' => 'tests'],
        );

        $this->assertSame($user->id, $record->user_id);
        $this->assertSame($subscription->id, $record->subscription_id);
        $this->assertSame('scale', $record->plan_key);
        $this->assertSame('metered_monthly', $record->price_key);
        $this->assertSame('api_requests', $record->meter_key);
        $this->assertSame(25, $record->quantity);
        $this->assertSame(['source' => 'tests'], $record->metadata);
    }

    public function test_it_calculates_usage_summaries_with_package_rounding(): void
    {
        $service = app(UsageMeterService::class);
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_key' => 'scale',
            'renews_at' => now()->addMonth(),
        ]);

        $price = new BillingPrice(
            key: 'metered_monthly',
            label: 'Metered Monthly',
            amount: 4900,
            currency: 'USD',
            interval: 'month',
            intervalCount: 1,
            type: PriceType::Recurring,
            hasTrial: false,
            trialInterval: null,
            trialIntervalCount: null,
            isMetered: true,
            usageMeterName: 'API requests',
            usageMeterKey: 'api_requests',
            usageUnitLabel: 'request',
            usageIncludedUnits: 10000,
            usagePackageSize: 1000,
            usageOverageAmount: 500,
            usageRoundingMode: 'up',
        );

        $service->record(
            user: $user,
            meterKey: 'api_requests',
            quantity: 11250,
            subscription: $subscription,
            priceKey: 'metered_monthly',
        );

        $summary = $service->summaryFor($user, $price, $subscription);

        $this->assertNotNull($summary);
        $this->assertSame(11250, $summary->usedUnits);
        $this->assertSame(10000, $summary->includedUnits);
        $this->assertSame(1250, $summary->overageUnits);
        $this->assertSame(2, $summary->billablePackages);
        $this->assertSame(1000, $summary->estimatedOverageAmountMinor);
    }

    public function test_blocking_metered_prices_report_quota_status_and_throw_when_limit_is_exceeded(): void
    {
        $service = app(UsageMeterService::class);
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'plan_key' => 'scale',
            'renews_at' => now()->addMonth(),
            'metadata' => [
                'price_key' => 'metered_monthly',
            ],
        ]);

        $price = new BillingPrice(
            key: 'metered_monthly',
            label: 'Metered Monthly',
            amount: 4900,
            currency: 'USD',
            interval: 'month',
            intervalCount: 1,
            type: PriceType::Recurring,
            hasTrial: false,
            trialInterval: null,
            trialIntervalCount: null,
            isMetered: true,
            usageMeterName: 'API requests',
            usageMeterKey: 'api_requests',
            usageUnitLabel: 'request',
            usageIncludedUnits: 100,
            usagePackageSize: 10,
            usageOverageAmount: 500,
            usageRoundingMode: 'up',
            usageLimitBehavior: UsageLimitBehavior::Block,
        );

        $service->record(
            user: $user,
            meterKey: 'api_requests',
            quantity: 95,
            subscription: $subscription,
            priceKey: 'metered_monthly',
        );

        $quotaStatus = $service->quotaStatusFor($user, $price, 10, $subscription);

        $this->assertNotNull($quotaStatus);
        $this->assertTrue($quotaStatus->blocksUsage);
        $this->assertTrue($quotaStatus->wouldBlockPendingUsage());
        $this->assertSame(5, $quotaStatus->remainingUnits);
        $this->assertSame(0, $quotaStatus->remainingUnitsAfterPending);
        $this->assertFalse($service->canConsume($user, $price, 10, $subscription));

        $this->expectException(UsageQuotaExceededException::class);

        $service->assertCanConsume($user, $price, 10, $subscription);
    }
}
