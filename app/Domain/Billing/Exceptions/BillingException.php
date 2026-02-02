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
    public static function providerError(\App\Enums\BillingProvider|string $provider, string $message, ?\Throwable $previous = null): self
    {
        $providerName = $provider instanceof \App\Enums\BillingProvider ? $provider->value : $provider;
        return new self(
            "Billing provider [{$providerName}] error: {$message}",

            'BILLING_PROVIDER_ERROR',
            0,
            $previous
        );
    }

    /**
     * Create exception for missing configuration.
     */
    public static function missingConfiguration(\App\Enums\BillingProvider|string $provider, string $key): self
    {
        $providerName = $provider instanceof \App\Enums\BillingProvider ? $provider->value : $provider;
        return new self(
            "{$providerName} {$key} is not configured.",
            'BILLING_MISSING_CONFIG'
        );
    }

    /**
     * Create exception for missing price ID.
     */
    public static function missingPriceId(\App\Enums\BillingProvider|string $provider, string $planKey, string $priceKey): self
    {
        $providerName = $provider instanceof \App\Enums\BillingProvider ? $provider->value : $provider;
        return new self(
            "{$providerName} price ID is missing for plan [{$planKey}] and price [{$priceKey}].",
            'BILLING_MISSING_PRICE_ID'
        );
    }

    /**
     * Create exception for webhook validation errors.
     */
    public static function webhookValidationFailed(\App\Enums\BillingProvider|string $provider, string $reason): self
    {
        $providerName = $provider instanceof \App\Enums\BillingProvider ? $provider->value : $provider;
        return new self(
            "{$providerName} webhook validation failed: {$reason}",
            'BILLING_WEBHOOK_INVALID'
        );
    }

    /**
     * Create exception for checkout creation failure.
     */
    public static function checkoutFailed(\App\Enums\BillingProvider|string $provider, string $reason): self
    {
        $providerName = $provider instanceof \App\Enums\BillingProvider ? $provider->value : $provider;
        return new self(
            "{$providerName} checkout creation failed: {$reason}",
            'BILLING_CHECKOUT_FAILED'
        );
    }

    /**
     * Create exception for provider action failure.
     */
    public static function failedAction(\App\Enums\BillingProvider|string $provider, string $action, string $reason): self
    {
        $providerName = $provider instanceof \App\Enums\BillingProvider ? $provider->value : $provider;
        return new self(
            "{$providerName} failed to {$action}: {$reason}",
            'BILLING_ACTION_FAILED'
        );
    }

    /**
     * Create exception for seat sync failure.
     */
    public static function seatSyncFailed(\App\Enums\BillingProvider|string $provider, string $reason): self
    {
        $providerName = $provider instanceof \App\Enums\BillingProvider ? $provider->value : $provider;
        return new self(
            "{$providerName} seat sync failed: {$reason}",
            'BILLING_SEAT_SYNC_FAILED'
        );
    }

    /**
     * Create exception for existing user.
     */
    public static function userAlreadyExists(string $email): self
    {
        return new self(
            "User with email [{$email}] already exists.",
            'BILLING_USER_ALREADY_EXISTS'
        );
    }
}
