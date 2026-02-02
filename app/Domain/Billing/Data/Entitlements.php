<?php

declare(strict_types=1);

namespace App\Domain\Billing\Data;

class Entitlements
{
    public function __construct(private readonly array $values) {}

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
    }

    public function value(\App\Enums\Feature $feature, mixed $default = null): mixed
    {
        return $this->get($feature->value, $default);
    }

    public function __get(string $key): mixed
    {
        return $this->get($key);
    }

    public function toArray(): array
    {
        return $this->values;
    }
}
