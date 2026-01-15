<?php

namespace App\Domain\Billing\Exceptions;

use RuntimeException;

/**
 * Base exception for billing-related errors.
 *
 * Provides specific factory methods for common billing error scenarios
 * with consistent error codes for API responses and logging.
 */
class BillingException extends RuntimeException
{
    /**
     * Error code for API responses.
     */
    protected string $errorCode;

    public function __construct(string $message, string $errorCode = 'BILLING_ERROR', int $code = 0, ?\Throwable $previous = null)
    {
        $this->errorCode = $errorCode;
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get the error code for API responses.
     */
    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    /**
     * Create exception for unknown plan.
     */
    public static function unknownPlan(string $planKey): self
    {
        return new self(
            "Unknown billing plan: {$planKey}",
            'BILLING_UNKNOWN_PLAN'
        );
    }

    /**
     * Create exception for unknown price.
     */
    public static function unknownPrice(string $planKey, string $priceKey): self
    {
        return new self(
            "Unknown price [{$priceKey}] for plan [{$planKey}].",
            'BILLING_UNKNOWN_PRICE'
        );
    }

    /**
     * Create exception for provider errors.
     */
    public static function providerError(string $provider, string $message, ?\Throwable $previous = null): self
    {
        return new self(
            "Billing provider [{$provider}] error: {$message}",
            'BILLING_PROVIDER_ERROR',
            0,
            $previous
        );
    }

    /**
     * Create exception for missing configuration.
     */
    public static function missingConfiguration(string $provider, string $key): self
    {
        return new self(
            "{$provider} {$key} is not configured.",
            'BILLING_MISSING_CONFIG'
        );
    }

    /**
     * Create exception for missing price ID.
     */
    public static function missingPriceId(string $provider, string $planKey, string $priceKey): self
    {
        return new self(
            "{$provider} price ID is missing for plan [{$planKey}] and price [{$priceKey}].",
            'BILLING_MISSING_PRICE_ID'
        );
    }

    /**
     * Create exception for webhook validation errors.
     */
    public static function webhookValidationFailed(string $provider, string $reason): self
    {
        return new self(
            "{$provider} webhook validation failed: {$reason}",
            'BILLING_WEBHOOK_INVALID'
        );
    }

    /**
     * Create exception for checkout creation failure.
     */
    public static function checkoutFailed(string $provider, string $reason): self
    {
        return new self(
            "{$provider} checkout creation failed: {$reason}",
            'BILLING_CHECKOUT_FAILED'
        );
    }

    /**
     * Create exception for seat sync failure.
     */
    public static function seatSyncFailed(string $provider, string $reason): self
    {
        return new self(
            "Failed to sync seats with {$provider}: {$reason}",
            'BILLING_SEAT_SYNC_FAILED'
        );
    }

    /**
     * Create exception for provider action failure.
     */
    public static function failedAction(string $provider, string $action, string $reason): self
    {
        return new self(
            "{$provider} failed to {$action}: {$reason}",
            'BILLING_ACTION_FAILED'
        );
    }
}
