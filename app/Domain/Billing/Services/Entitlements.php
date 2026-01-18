<?php

namespace App\Domain\Billing\Services;

class Entitlements
{
    public function __construct(private readonly array $values)
    {
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->values[$key] ?? $default;
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
