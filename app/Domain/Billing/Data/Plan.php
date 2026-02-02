<?php

namespace App\Domain\Billing\Data;

readonly class Plan
{
    /**
     * @param  array<string, Price>  $prices
     */
    public function __construct(
        public string $key,
        public string $name,
        public string $summary,
        public \App\Enums\PriceType $type,
        public bool $highlight,
        public array $features,
        public array $entitlements,
        public array $prices,
        public bool $isAvailable = true,
    ) {}

    public function getPrice(string $key): ?Price
    {
        return $this->prices[$key] ?? null;
    }

    public function firstPrice(): ?Price
    {
        return empty($this->prices) ? null : reset($this->prices);
    }

    public function isOneTime(): bool
    {
        return $this->type === \App\Enums\PriceType::OneTime;
    }
}
