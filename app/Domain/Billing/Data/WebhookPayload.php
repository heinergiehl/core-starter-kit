<?php

namespace App\Domain\Billing\Data;

/**
 * Data Transfer Object for normalized webhook event data.
 *
 * Provides a consistent structure for webhook events across all
 * billing providers before persistence.
 */
readonly class WebhookPayload
{
    /**
     * @param string $id Unique event ID from the provider
     * @param string|null $type Event type (e.g., 'checkout.session.completed')
     * @param array<string, mixed> $payload Raw event data from provider
     * @param string $provider The billing provider name
     */
    public function __construct(
        public string $id,
        public ?string $type,
        public array $payload,
        public string $provider,
    ) {}

    /**
     * Get the main object/data from the payload.
     *
     * @return array<string, mixed>
     */
    public function object(): array
    {
        return data_get($this->payload, 'data.object', $this->payload);
    }
}
