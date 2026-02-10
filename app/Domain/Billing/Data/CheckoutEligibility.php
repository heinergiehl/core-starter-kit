<?php

namespace App\Domain\Billing\Data;

readonly class CheckoutEligibility
{
    public function __construct(
        public bool $allowed,
        public ?string $errorCode = null,
        public ?string $message = null,
        public bool $isUpgrade = false,
    ) {}

    public static function allow(bool $isUpgrade = false): self
    {
        return new self(
            allowed: true,
            isUpgrade: $isUpgrade,
        );
    }

    public static function deny(string $errorCode, string $message): self
    {
        return new self(
            allowed: false,
            errorCode: $errorCode,
            message: $message,
        );
    }
}

