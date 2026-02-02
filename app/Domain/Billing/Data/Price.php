<?php

namespace App\Domain\Billing\Data;

use App\Enums\PaymentMode;

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
}
