<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Data\Price;
use App\Domain\Billing\Services\PricePresentationService;
use App\Enums\PriceType;
use App\Enums\UsageLimitBehavior;
use Tests\TestCase;

class PricePresentationServiceTest extends TestCase
{
    private function makePrice(array $overrides = []): Price
    {
        return new Price(
            key: $overrides['key'] ?? 'monthly',
            label: $overrides['label'] ?? 'Monthly',
            amount: $overrides['amount'] ?? 4999,
            currency: $overrides['currency'] ?? 'USD',
            interval: $overrides['interval'] ?? 'month',
            intervalCount: $overrides['intervalCount'] ?? 1,
            type: $overrides['type'] ?? PriceType::Recurring,
            hasTrial: $overrides['hasTrial'] ?? false,
            trialInterval: $overrides['trialInterval'] ?? null,
            trialIntervalCount: $overrides['trialIntervalCount'] ?? null,
            allowCustomAmount: $overrides['allowCustomAmount'] ?? false,
            isMetered: $overrides['isMetered'] ?? false,
            usageMeterName: $overrides['usageMeterName'] ?? null,
            usageMeterKey: $overrides['usageMeterKey'] ?? null,
            usageUnitLabel: $overrides['usageUnitLabel'] ?? null,
            usageIncludedUnits: $overrides['usageIncludedUnits'] ?? null,
            usagePackageSize: $overrides['usagePackageSize'] ?? null,
            usageOverageAmount: $overrides['usageOverageAmount'] ?? null,
            usageRoundingMode: $overrides['usageRoundingMode'] ?? null,
            usageLimitBehavior: $overrides['usageLimitBehavior'] ?? UsageLimitBehavior::BillOverage,
            customAmountMinimum: $overrides['customAmountMinimum'] ?? null,
            customAmountMaximum: $overrides['customAmountMaximum'] ?? null,
            customAmountDefault: $overrides['customAmountDefault'] ?? null,
            suggestedAmounts: $overrides['suggestedAmounts'] ?? [],
            providerIds: $overrides['providerIds'] ?? [],
            amountIsMinor: $overrides['amountIsMinor'] ?? true,
        );
    }

    public function test_fixed_price_returns_formatted_amount(): void
    {
        $service = new PricePresentationService;
        $price = $this->makePrice(['amount' => 4999, 'currency' => 'USD']);

        $result = $service->forPricingCard($price);

        $this->assertNotEmpty($result['amountDisplay']);
        $this->assertSame('USD', $result['currency']);
        $this->assertSame('month', $result['interval']);
        $this->assertFalse($result['supportsCustomAmount']);
        $this->assertFalse($result['isMetered']);
        $this->assertNull($result['pricingModeBadge']);
        $this->assertSame(4999, $result['targetAmountMinor']);
    }

    public function test_pwyw_price_returns_custom_amount_badge(): void
    {
        $service = new PricePresentationService;
        $price = $this->makePrice([
            'allowCustomAmount' => true,
            'customAmountMinimum' => 500,
            'customAmountMaximum' => 50000,
            'customAmountDefault' => 2500,
            'type' => PriceType::OneTime,
            'interval' => 'once',
        ]);

        $result = $service->forPricingCard($price);

        $this->assertTrue($result['supportsCustomAmount']);
        $this->assertSame(__('Pay what you want'), $result['pricingModeBadge']);
        $this->assertSame('violet', $result['pricingModeBadgeColor']);
        $this->assertSame(2500, $result['targetAmountMinor']);
        $this->assertNotNull($result['amountMeta']);
    }

    public function test_metered_price_returns_usage_badge(): void
    {
        $service = new PricePresentationService;
        $price = $this->makePrice([
            'isMetered' => true,
            'usageMeterName' => 'API Calls',
            'usageMeterKey' => 'api_calls',
            'usageUnitLabel' => 'call',
            'usageIncludedUnits' => 1000,
            'usageOverageAmount' => 10,
            'usagePackageSize' => 100,
            'usageLimitBehavior' => UsageLimitBehavior::BillOverage,
        ]);

        $result = $service->forPricingCard($price);

        $this->assertTrue($result['isMetered']);
        $this->assertFalse($result['usageBlocksAtLimit']);
        $this->assertSame(__('Usage-based'), $result['pricingModeBadge']);
        $this->assertSame('emerald', $result['pricingModeBadgeColor']);
        $this->assertNotNull($result['amountCaption']);
        $this->assertNotNull($result['amountMeta']);
    }

    public function test_metered_price_with_block_at_limit_behavior(): void
    {
        $service = new PricePresentationService;
        $price = $this->makePrice([
            'isMetered' => true,
            'usageMeterName' => 'Storage',
            'usageMeterKey' => 'storage',
            'usageUnitLabel' => 'GB',
            'usageIncludedUnits' => 50,
            'usageLimitBehavior' => UsageLimitBehavior::Block,
        ]);

        $result = $service->forPricingCard($price);

        $this->assertTrue($result['usageBlocksAtLimit']);
        $this->assertStringContainsString(__('blocked'), strtolower($result['amountMeta']));
    }

    public function test_interval_labels_contains_expected_keys(): void
    {
        $service = new PricePresentationService;
        $labels = $service->intervalLabels();

        $this->assertArrayHasKey('month', $labels);
        $this->assertArrayHasKey('year', $labels);
        $this->assertArrayHasKey('once', $labels);
    }

    public function test_one_time_price_has_lifetime_interval(): void
    {
        $service = new PricePresentationService;
        $price = $this->makePrice([
            'interval' => 'once',
            'type' => PriceType::OneTime,
        ]);

        $result = $service->forPricingCard($price);

        $this->assertSame(__('Lifetime'), $result['intervalLabel']);
    }

    public function test_trial_badge_returned_for_price_with_trial(): void
    {
        $service = new PricePresentationService;
        $price = $this->makePrice([
            'hasTrial' => true,
            'trialInterval' => 'day',
            'trialIntervalCount' => 14,
        ]);

        $result = $service->forPricingCard($price);

        $this->assertTrue($result['hasTrial']);
        $this->assertSame(14, $result['trialDays']);
        $this->assertNotNull($result['trialLabel']);
        $this->assertStringContainsString('14', $result['trialLabel']);
    }

    public function test_no_trial_badge_when_trial_not_set(): void
    {
        $service = new PricePresentationService;
        $price = $this->makePrice(['hasTrial' => false]);

        $result = $service->forPricingCard($price);

        $this->assertFalse($result['hasTrial']);
        $this->assertNull($result['trialDays']);
        $this->assertNull($result['trialLabel']);
    }

    public function test_pwyw_suggested_amounts_formatted(): void
    {
        $service = new PricePresentationService;
        $price = $this->makePrice([
            'allowCustomAmount' => true,
            'customAmountDefault' => 1000,
            'suggestedAmounts' => [500, 1000, 2500],
            'type' => PriceType::OneTime,
            'interval' => 'once',
        ]);

        $result = $service->forPricingCard($price);

        $this->assertCount(3, $result['suggestedAmounts']);
        $this->assertSame(500, $result['suggestedAmounts'][0]['minor']);
        $this->assertNotEmpty($result['suggestedAmounts'][0]['formatted']);
        $this->assertSame(2500, $result['suggestedAmounts'][2]['minor']);
    }

    public function test_pwyw_min_max_formatted(): void
    {
        $service = new PricePresentationService;
        $price = $this->makePrice([
            'allowCustomAmount' => true,
            'customAmountMinimum' => 500,
            'customAmountMaximum' => 10000,
            'customAmountDefault' => 2000,
            'type' => PriceType::OneTime,
            'interval' => 'once',
        ]);

        $result = $service->forPricingCard($price);

        $this->assertNotNull($result['customAmountMinimumFormatted']);
        $this->assertNotNull($result['customAmountMaximumFormatted']);
        $this->assertNotNull($result['customAmountDefaultFormatted']);
    }

    public function test_metered_price_returns_included_units(): void
    {
        $service = new PricePresentationService;
        $price = $this->makePrice([
            'isMetered' => true,
            'usageMeterName' => 'API Calls',
            'usageMeterKey' => 'api_calls',
            'usageUnitLabel' => 'request',
            'usageIncludedUnits' => 5000,
            'usageOverageAmount' => 5,
            'usagePackageSize' => 1000,
            'usageLimitBehavior' => UsageLimitBehavior::BillOverage,
        ]);

        $result = $service->forPricingCard($price);

        $this->assertSame(5000, $result['usageIncludedUnits']);
    }

    public function test_trial_with_one_day(): void
    {
        $service = new PricePresentationService;
        $price = $this->makePrice([
            'hasTrial' => true,
            'trialInterval' => 'day',
            'trialIntervalCount' => 1,
        ]);

        $result = $service->forPricingCard($price);

        $this->assertTrue($result['hasTrial']);
        $this->assertSame(1, $result['trialDays']);
        $this->assertStringContainsString('1', $result['trialLabel']);
    }

    public function test_trial_with_month_interval(): void
    {
        $service = new PricePresentationService;
        $price = $this->makePrice([
            'hasTrial' => true,
            'trialInterval' => 'month',
            'trialIntervalCount' => 1,
        ]);

        $result = $service->forPricingCard($price);

        $this->assertTrue($result['hasTrial']);
        $this->assertNotNull($result['trialLabel']);
    }
}
