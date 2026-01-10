<?php

namespace App\Domain\Billing\Data;

/**
 * Data Transfer Object for checkout session response.
 *
 * Encapsulates the result of creating a checkout session.
 */
readonly class CheckoutSession
{
    /**
     * @param string $url The checkout URL to redirect the user to
     * @param string|null $sessionId Optional session ID from the provider
     * @param string $provider The billing provider (e.g., 'stripe', 'paddle')
     */
    public function __construct(
        public string $url,
        public ?string $sessionId = null,
        public string $provider = 'unknown',
    ) {}
}
