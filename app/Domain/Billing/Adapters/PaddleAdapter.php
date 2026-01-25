<?php

namespace App\Domain\Billing\Adapters;

use App\Domain\Billing\Adapters\Paddle\Concerns\ResolvesPaddleData;
use App\Domain\Billing\Adapters\Paddle\Handlers\PaddleOrderHandler;
use App\Domain\Billing\Adapters\Paddle\Handlers\PaddlePriceHandler;
use App\Domain\Billing\Adapters\Paddle\Handlers\PaddleProductHandler;
use App\Domain\Billing\Adapters\Paddle\Handlers\PaddleSubscriptionHandler;
use App\Domain\Billing\Contracts\PaddleWebhookHandler;
use App\Domain\Billing\Exceptions\BillingException;
use App\Domain\Billing\Models\BillingCustomer;
use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\WebhookEvent;
use App\Domain\Billing\Services\BillingPlanService;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/**
 * Paddle billing provider adapter.
 *
 * This adapter handles all Paddle-related billing operations including:
 * - Webhook parsing and processing
 * - Checkout session creation
 *
 * Webhook event processing is delegated to specialized handlers for
 * maintainability and single responsibility.
 *
 * @see PaddleProductHandler
 * @see PaddlePriceHandler
 * @see PaddleSubscriptionHandler
 * @see PaddleOrderHandler
 */
class PaddleAdapter implements BillingProviderAdapter
{
    use ResolvesPaddleData;

    /**
     * Registered webhook handlers.
     *
     * @var array<string, PaddleWebhookHandler>
     */
    private array $handlers = [];

    public function __construct()
    {
        $this->registerHandlers();
    }

    public function provider(): string
    {
        return 'paddle';
    }

    public function parseWebhook(Request $request): array
    {
        $payload = $request->getContent();
        $signature = $request->header('Paddle-Signature');
        $secret = config('services.paddle.webhook_secret');

        // Verify signature in production
        if (! app()->environment(['local', 'testing'])) {
            if (! $secret) {
                throw BillingException::missingConfiguration('Paddle', 'webhook secret');
            }

            if (! $signature) {
                throw BillingException::webhookValidationFailed('Paddle', 'signature header is missing');
            }

            if (! $this->verifyPaddleSignature($payload, $signature, $secret)) {
                throw BillingException::webhookValidationFailed('Paddle', 'invalid signature');
            }
        }

        $data = json_decode($payload, true);
        if (! is_array($data)) {
            throw BillingException::webhookValidationFailed('Paddle', 'invalid payload structure');
        }

        $eventId = $data['event_id'] ?? $data['id'] ?? null;
        $eventType = $data['event_type'] ?? $data['type'] ?? null;

        if (! $eventId || ! $eventType) {
            throw BillingException::webhookValidationFailed('Paddle', 'missing event id or type');
        }

        return [
            'id' => (string) $eventId,
            'type' => $eventType,
            'payload' => $data,
        ];
    }

    /**
     * Verify Paddle webhook signature using HMAC-SHA256.
     */
    private function verifyPaddleSignature(string $payload, string $signature, string $secret): bool
    {
        // Paddle v2 signature format: ts=timestamp;h1=hash
        $parts = [];
        foreach (explode(';', $signature) as $part) {
            [$key, $value] = explode('=', $part, 2) + [null, null];
            if ($key && $value) {
                $parts[$key] = $value;
            }
        }

        $timestamp = $parts['ts'] ?? null;
        $hash = $parts['h1'] ?? null;

        if (! $timestamp || ! $hash || ! ctype_digit((string) $timestamp)) {
            return false;
        }

        $timestamp = (int) $timestamp;
        $toleranceSeconds = (int) config('services.paddle.webhook_tolerance_seconds', 300);
        if ($toleranceSeconds > 0 && abs(time() - $timestamp) > $toleranceSeconds) {
            return false;
        }

        // Build signed payload: timestamp:payload
        $signedPayload = $timestamp.':'.$payload;
        $expectedHash = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($expectedHash, $hash);
    }

    public function processEvent(WebhookEvent $event): void
    {
        $payload = $event->payload ?? [];
        $type = $payload['event_type'] ?? $payload['type'] ?? $event->type;
        $data = $payload['data'] ?? $payload;

        if (! $type || ! $data) {
            return;
        }

        $handler = $this->getHandler($type);

        if ($handler) {
            $handler->handle($event, $data);
        }
    }

    /**
     * Register webhook handlers.
     */
    private function registerHandlers(): void
    {
        $handlers = [
            new PaddleProductHandler,
            new PaddlePriceHandler,
            app(PaddleSubscriptionHandler::class),
            new PaddleOrderHandler,
        ];

        foreach ($handlers as $handler) {
            foreach ($handler->eventTypes() as $eventType) {
                $this->handlers[$eventType] = $handler;
            }
        }
    }

    /**
     * Get the handler for a specific event type.
     */
    private function getHandler(string $eventType): ?PaddleWebhookHandler
    {
        return $this->handlers[$eventType] ?? null;
    }

    public function createCheckout(
        User $user,
        string $planKey,
        string $priceKey,
        int $quantity,
        string $successUrl,
        string $cancelUrl,
        ?Discount $discount = null
    ): string {
        $payload = $this->buildTransactionPayload(
            $user,
            $planKey,
            $priceKey,
            $quantity,
            $successUrl,
            $cancelUrl,
            $discount,
            []
        );

        $data = $this->createTransaction($payload);

        $url = data_get($data, 'checkout.url')
            ?? data_get($data, 'url')
            ?? data_get($data, 'checkout_url');

        if (! $url) {
            throw BillingException::checkoutFailed('Paddle', 'checkout URL was not returned');
        }

        return $url;
    }

    public function createTransactionId(
        ?User $user,
        string $planKey,
        string $priceKey,
        int $quantity,
        string $successUrl,
        string $cancelUrl,
        ?Discount $discount = null,
        array $extraCustomData = [],
        ?string $customerEmail = null
    ): string {
        $payload = $this->buildTransactionPayload(
            $user,
            $planKey,
            $priceKey,
            $quantity,
            $successUrl,
            $cancelUrl,
            $discount,
            $extraCustomData,
            $customerEmail
        );

        $data = $this->createTransaction($payload);

        $transactionId = data_get($data, 'id') ?? data_get($data, 'transaction_id');

        if (! $transactionId) {
            throw BillingException::checkoutFailed('Paddle', 'transaction id was not returned');
        }

        return (string) $transactionId;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTransactionPayload(
        ?User $user,
        string $planKey,
        string $priceKey,
        int $quantity,
        string $successUrl,
        string $cancelUrl,
        ?Discount $discount = null,
        array $extraCustomData = [],
        ?string $customerEmail = null
    ): array {
        $planService = app(BillingPlanService::class);
        $priceId = $planService->providerPriceId($this->provider(), $planKey, $priceKey);

        if (! $priceId) {
            throw BillingException::missingPriceId('Paddle', $planKey, $priceKey);
        }

        $customData = [
            'user_id' => $user?->id,
            'plan_key' => $planKey,
            'price_key' => $priceKey,
        ];

        if ($discount) {
            $customData['discount_id'] = $discount->id;
            $customData['discount_code'] = $discount->code;
        }

        if (! empty($extraCustomData)) {
            $customData = array_merge($customData, $extraCustomData);
        }

        $customData = array_filter(
            $customData,
            static fn ($value) => $value !== null && $value !== ''
        );

        $payload = [
            'items' => [
                [
                    'price_id' => $priceId,
                    'quantity' => max($quantity, 1),
                ],
            ],
            'custom_data' => $customData,
            'checkout' => [
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ],
        ];

        $customerId = null;
        if ($user) {
            $customerId = BillingCustomer::query()
                ->where('user_id', $user->id)
                ->where('provider', $this->provider())
                ->value('provider_id');
        }

        if ($customerId) {
            $payload['customer_id'] = $customerId;
        } else {
            $email = $customerEmail ?: $user?->email;
            if ($email) {
                $payload['customer'] = [
                    'email' => $email,
                ];
            }
        }

        if ($discount) {
            $payload['discount_id'] = $discount->provider_id;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function createTransaction(array $payload): array
    {
        $apiKey = config('services.paddle.api_key');

        if (! $apiKey) {
            throw BillingException::missingConfiguration('Paddle', 'api_key');
        }

        $response = $this->paddleRequest($apiKey)
            ->post('/transactions', $payload);

        if (! $response->successful()) {
            throw BillingException::failedAction('Paddle', 'create transaction', $response->body());
        }

        return $response->json('data') ?? [];
    }

    public function createDiscount(Discount $discount): string
    {
        $apiKey = config('services.paddle.api_key');
        if (! $apiKey) {
            throw BillingException::missingConfiguration('Paddle', 'api_key');
        }

        $payload = [
            'description' => $discount->name ?? $discount->code,
            'type' => $discount->type === 'percent' ? 'percentage' : 'flat',
            'amount' => (string) $discount->amount,
            'currency_code' => $discount->currency ?? 'USD',
            'code' => $discount->code,
        ];

        if ($discount->max_redemptions) {
            $payload['usage_limit'] = $discount->max_redemptions;
        }

        if ($discount->ends_at) {
            $payload['expires_at'] = $discount->ends_at->toIso8601String();
        }

        $response = $this->paddleRequest($apiKey)
            ->post('/discounts', $payload);

        if (! $response->successful()) {
            throw BillingException::failedAction('Paddle', 'create discount', $response->body());
        }

        return $response->json('data.id');
    }

    private function paddleRequest(string $apiKey): PendingRequest
    {
        $timeout = (int) config('saas.billing.provider_api.timeouts.paddle', 15);
        $connectTimeout = (int) config('saas.billing.provider_api.connect_timeouts.paddle', 5);
        $retries = (int) config('saas.billing.provider_api.retries.paddle', 2);
        $retryDelay = (int) config('saas.billing.provider_api.retry_delay_ms', 500);

        return Http::withToken($apiKey)
            ->acceptJson()
            ->baseUrl($this->paddleBaseUrl())
            ->timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->retry(
                $retries,
                $retryDelay,
                fn ($exception, $request = null, $method = null): bool => $this->shouldRetryProviderRequest($exception),
                false
            );
    }

    private function paddleBaseUrl(): string
    {
        $environment = config('services.paddle.environment', 'production');

        return $environment === 'sandbox'
            ? 'https://sandbox-api.paddle.com'
            : 'https://api.paddle.com';
    }

    private function shouldRetryProviderRequest(\Throwable $exception): bool
    {
        if ($exception instanceof \Illuminate\Http\Client\RequestException) {
            $response = $exception->response;
            if (! $response) {
                return true;
            }

            return $response->serverError()
                || $response->status() === 429
                || $response->status() === 408;
        }

        return true;
    }

    public function updateSubscription(\App\Domain\Billing\Models\Subscription $subscription, string $newPriceId): void
    {
        $apiKey = config('services.paddle.api_key');

        // Get current subscription to find quantity
        $quantity = $subscription->quantity ?? 1;

        // Update subscription with new price
        $response = $this->paddleRequest($apiKey)
            ->patch("/subscriptions/{$subscription->provider_id}", [
                'items' => [
                    [
                        'price_id' => $newPriceId,
                        'quantity' => $quantity,
                    ],
                ],
                'proration_billing_mode' => 'prorated_immediately',
            ]);

        if (! $response->successful()) {
            throw BillingException::failedAction('Paddle', 'update subscription', $response->body());
        }
    }

    public function cancelSubscription(\App\Domain\Billing\Models\Subscription $subscription): \Carbon\Carbon
    {
        $apiKey = config('services.paddle.api_key');

        // Cancel at next billing date (graceful cancellation)
        $response = $this->paddleRequest($apiKey)
            ->post("/subscriptions/{$subscription->provider_id}/cancel", [
                'effective_from' => 'next_billing_period',
            ]);

        // Handle case where locked pending changes exist (try resuming first then cancelling again)
        if (! $response->successful()
            && data_get($response->json(), 'error.code') === 'subscription_locked_pending_changes'
        ) {
            $this->resumeSubscription($subscription);

            $response = $this->paddleRequest($apiKey)
                ->post("/subscriptions/{$subscription->provider_id}/cancel", [
                    'effective_from' => 'next_billing_period',
                ]);
        }

        if (! $response->successful()) {
            throw BillingException::failedAction('Paddle', 'cancel subscription', $response->body());
        }

        // Get the scheduled cancel date
        $scheduledChange = $response->json('data.scheduled_change');
        $endsAt = $scheduledChange['effective_at'] ?? null;

        return $endsAt
            ? \Carbon\Carbon::parse($endsAt)
            : ($subscription->renews_at ?? now()->addMonth());
    }

    public function resumeSubscription(\App\Domain\Billing\Models\Subscription $subscription): void
    {
        $apiKey = config('services.paddle.api_key');

        // Remove scheduled cancellation by setting scheduled_change to null
        $response = $this->paddleRequest($apiKey)
            ->patch("/subscriptions/{$subscription->provider_id}", [
                'scheduled_change' => null,
            ]);

        if (! $response->successful()) {
            throw BillingException::failedAction('Paddle', 'resume subscription', $response->body());
        }
    }
}
