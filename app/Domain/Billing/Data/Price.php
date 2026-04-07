<?php

namespace App\Domain\Billing\Data;

use App\Enums\PaymentMode;
use App\Enums\UsageLimitBehavior;

readonly class Price
{
    public function __construct(
        public string $key,
        public string $label,
        public int|float $amount,
        public string $currency,
        public string $interval,
        public int $intervalCount,
        public \App\Enums\PriceType $type,
        public bool $hasTrial,
        public ?string $trialInterval,
        public ?int $trialIntervalCount,
        public bool $allowCustomAmount = false,
        public bool $isMetered = false,
        public ?string $usageMeterName = null,
        public ?string $usageMeterKey = null,
        public ?string $usageUnitLabel = null,
        public ?int $usageIncludedUnits = null,
        public ?int $usagePackageSize = null,
        public ?int $usageOverageAmount = null,
        public ?string $usageRoundingMode = null,
        public UsageLimitBehavior $usageLimitBehavior = UsageLimitBehavior::BillOverage,
        public ?int $customAmountMinimum = null,
        public ?int $customAmountMaximum = null,
        public ?int $customAmountDefault = null,
        public array $suggestedAmounts = [],
        public array $providerIds = [],
        public array $providerAmounts = [],
        public array $providerCurrencies = [],
        public ?string $contextProviderId = null,
        public bool $isAvailable = true,
        public bool $amountIsMinor = true,
    ) {}

    public function idFor(string $provider): ?string
    {
        if ($this->contextProviderId) {
            return $this->contextProviderId;
        }

        return $this->providerIds[$provider] ?? null;
    }

    public function amountFor(string $provider): int|float
    {
        return $this->providerAmounts[$provider] ?? $this->amount;
    }

    public function currencyFor(string $provider): string
    {
        return $this->providerCurrencies[$provider] ?? $this->currency;
    }

    public function mode(): PaymentMode
    {
        if ($this->type === \App\Enums\PriceType::OneTime || $this->interval === 'once' || empty($this->interval)) {
            return PaymentMode::OneTime;
        }

        return PaymentMode::Subscription;
    }

    public function supportsCustomAmount(): bool
    {
        return $this->allowCustomAmount && $this->mode() === PaymentMode::OneTime;
    }

    public function isMetered(): bool
    {
        return $this->isMetered && $this->mode() === PaymentMode::Subscription && filled($this->usageMeterKey);
    }

    public function allowsOverageBilling(): bool
    {
        return $this->isMetered() && $this->usageLimitBehavior->allowsOverageBilling();
    }

    public function blocksUsageAtLimit(): bool
    {
        return $this->isMetered() && $this->usageLimitBehavior->blocksUsage();
    }
}
