<?php

declare(strict_types=1);

namespace App\Domain\Billing\Adapters;

use App\Domain\Billing\Adapters\Paddle\Concerns\ResolvesPaddleData;
use App\Domain\Billing\Adapters\Paddle\Handlers\PaddleOrderHandler;
use App\Domain\Billing\Adapters\Paddle\Handlers\PaddleSubscriptionHandler;
use App\Domain\Billing\Contracts\BillingRuntimeProvider;
use App\Domain\Billing\Contracts\PaddleWebhookHandler;
use App\Domain\Billing\Data\TransactionDTO;
use App\Domain\Billing\Data\WebhookPayload;
use App\Domain\Billing\Exceptions\BillingException;
use App\Domain\Billing\Models\BillingCustomer;
use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\WebhookEvent;
use App\Domain\Billing\Services\BillingPlanService;
use App\Enums\BillingProvider;
use App\Enums\DiscountType;
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
 * @see PaddleSubscriptionHandler
 * @see PaddleOrderHandler
 */
class PaddleAdapter implements BillingRuntimeProvider
{
    use ResolvesPaddleData;

    private const SIGNATURE_HEADER = 'Paddle-Signature';

    private const SANDBOX_API_URL = 'https://sandbox-api.paddle.com';

    private const PRODUCTION_API_URL = 'https://api.paddle.com';

    private const WEBHOOK_TOLERANCE_SECONDS = 300;

    /**
     * Registered webhook handlers.
     *
     * @var array<string, PaddleWebhookHandler>
     */
    private array $handlers = [];

    public function __construct(
        private readonly array $config,
        private readonly BillingPlanService $planService,
    ) {
        $this->registerHandlers();
    }

    public function provider(): string
    {
        return BillingProvider::Paddle->value;
    }

    public function parseWebhook(Request $request): WebhookPayload
    {
        $payload = $request->getContent();
        $signature = $request->header(self::SIGNATURE_HEADER);
        $secret = $this->config['webhook_secret'] ?? null;

        // Verify signature in production
        if (! app()->environment(['local', 'testing'])) {
            if (! $secret) {
                throw BillingException::missingConfiguration(BillingProvider::Paddle, 'webhook secret');
            }

            if (! $signature) {
                throw BillingException::webhookValidationFailed(BillingProvider::Paddle, 'signature header is missing');
            }

            if (! $this->verifyPaddleSignature($payload, $signature, $secret)) {
                throw BillingException::webhookValidationFailed(BillingProvider::Paddle, 'invalid signature');
            }
        }

        $data = json_decode($payload, true);
        if (! is_array($data)) {
            throw BillingException::webhookValidationFailed(BillingProvider::Paddle, 'invalid payload structure');
        }

        $eventId = $data['event_id'] ?? $data['id'] ?? null;
        $eventType = $data['event_type'] ?? $data['type'] ?? null;

        if (! $eventId || ! $eventType) {
            throw BillingException::webhookValidationFailed(BillingProvider::Paddle, 'missing event id or type');
        }

        return new WebhookPayload(
            id: (string) $eventId,
            type: $eventType,
            payload: $data,
            provider: $this->provider()
        );
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
        $toleranceSeconds = (int) ($this->config['webhook_tolerance_seconds'] ?? self::WEBHOOK_TOLERANCE_SECONDS);
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
     *
     * NOTE: Product/Price handlers are intentionally NOT registered.
     * App-first mode = products/prices are managed in app, not synced from Paddle webhooks.
     */
    private function registerHandlers(): void
    {
        $handlers = [
            // PaddleProductHandler and PaddlePriceHandler REMOVED for app-first mode
            app(PaddleSubscriptionHandler::class),
            app(PaddleOrderHandler::class),
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
    ): TransactionDTO {
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

        return $this->createTransaction($payload);
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
    ): TransactionDTO {
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

        return $this->createTransaction($payload);
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
        $priceId = $this->planService->providerPriceId($this->provider(), $planKey, $priceKey);

        if (! $priceId) {
            throw BillingException::missingPriceId(BillingProvider::Paddle, $planKey, $priceKey);
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
    private function createTransaction(array $payload): TransactionDTO
    {
        $apiKey = $this->config['api_key'] ?? null;

        if (! $apiKey) {
            throw BillingException::missingConfiguration(BillingProvider::Paddle, 'api_key');
        }

        $response = $this->paddleRequest($apiKey)
            ->post('/transactions', $payload);

        if (! $response->successful()) {
            throw BillingException::failedAction(BillingProvider::Paddle, 'create transaction', $response->body());
        }

        $data = $response->json('data') ?? [];
        $id = data_get($data, 'id') ?? data_get($data, 'transaction_id');
        $url = data_get($data, 'checkout.url')
            ?? data_get($data, 'url')
            ?? data_get($data, 'checkout_url');

        // Status is sometimes 'status', sometimes 'state' depending on endpoint version/type
        $status = data_get($data, 'status') ?? data_get($data, 'state');

        if (! $id) {
            throw BillingException::checkoutFailed(BillingProvider::Paddle, 'transaction id was not returned');
        }

        /* If URL is missing, we might need to handle it, but for now we trust Paddle or fallbacks */

        return new TransactionDTO(
            id: (string) $id,
            url: (string) $url,
            status: (string) $status
        );
    }

    public function createDiscount(Discount $discount): string
    {
        $apiKey = $this->config['api_key'] ?? null;
        if (! $apiKey) {
            throw BillingException::missingConfiguration(BillingProvider::Paddle, 'api_key');
        }

        $payload = [
            'description' => $discount->name ?? $discount->code,
            'type' => $discount->type === DiscountType::Percent->value ? 'percentage' : 'flat',
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
            throw BillingException::failedAction(BillingProvider::Paddle, 'create discount', $response->body());
        }

        return $response->json('data.id');
    }

    private function paddleRequest(string $apiKey): PendingRequest
    {
        $timeout = (int) ($this->config['timeout'] ?? 15);
        $connectTimeout = (int) ($this->config['connect_timeout'] ?? 5);
        $retries = (int) ($this->config['retries'] ?? 2);
        $retryDelay = (int) ($this->config['retry_delay_ms'] ?? 500);

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
        $environment = $this->config['environment'] ?? 'production';

        return $environment === 'sandbox'
            ? self::SANDBOX_API_URL
            : self::PRODUCTION_API_URL;
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

    public function updateSubscription(Subscription $subscription, string $newPriceId): void
    {
        $apiKey = $this->config['api_key'] ?? null;
        if (! $apiKey) {
            throw BillingException::missingConfiguration(BillingProvider::Paddle, 'api_key');
        }

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
            throw BillingException::failedAction(BillingProvider::Paddle, 'update subscription', $response->body());
        }
    }

    public function syncSubscriptionState(Subscription $subscription): void
    {
        $apiKey = $this->config['api_key'] ?? null;
        if (! $apiKey) {
            throw BillingException::missingConfiguration(BillingProvider::Paddle, 'api_key');
        }

        $response = $this->paddleRequest($apiKey)
            ->get("/subscriptions/{$subscription->provider_id}", [
                'include' => 'next_transaction',
            ]);

        if (! $response->successful()) {
            throw BillingException::failedAction(BillingProvider::Paddle, 'sync subscription state', $response->body());
        }

        $data = $response->json('data');
        if (! is_array($data) || $data === []) {
            throw BillingException::failedAction(BillingProvider::Paddle, 'sync subscription state', 'subscription payload is empty');
        }

        $priceId = data_get($data, 'items.0.price_id')
            ?? data_get($data, 'items.0.price.id')
            ?? null;

        if ($priceId && ! data_get($data, 'items.0.price_id')) {
            data_set($data, 'items.0.price_id', $priceId);
        }

        $customData = data_get($data, 'custom_data');
        if (! is_array($customData)) {
            $customData = [];
        }

        if (! array_key_exists('user_id', $customData) || $customData['user_id'] === null || $customData['user_id'] === '') {
            $customData['user_id'] = $subscription->user_id;
        }

        $resolvedPlanKey = $priceId
            ? $this->planService->resolvePlanKeyByProviderId($this->provider(), (string) $priceId)
            : null;
        if ($resolvedPlanKey) {
            $customData['plan_key'] = $resolvedPlanKey;
        }

        data_set($data, 'custom_data', $customData);

        app(PaddleSubscriptionHandler::class)->syncSubscription($data);
    }

    public function cancelSubscription(Subscription $subscription): \Carbon\Carbon
    {
        $apiKey = $this->config['api_key'] ?? null;
        if (! $apiKey) {
            throw BillingException::missingConfiguration(BillingProvider::Paddle, 'api_key');
        }

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
            throw BillingException::failedAction(BillingProvider::Paddle, 'cancel subscription', $response->body());
        }

        // Get the scheduled cancel date
        $scheduledChange = $response->json('data.scheduled_change');
        $endsAt = $scheduledChange['effective_at'] ?? null;

        return $endsAt
            ? \Carbon\Carbon::parse($endsAt)
            : ($subscription->renews_at ?? now()->addMonth());
    }

    public function resumeSubscription(Subscription $subscription): void
    {
        $apiKey = $this->config['api_key'] ?? null;
        if (! $apiKey) {
            throw BillingException::missingConfiguration(BillingProvider::Paddle, 'api_key');
        }

        // Remove scheduled cancellation by setting scheduled_change to null
        $response = $this->paddleRequest($apiKey)
            ->patch("/subscriptions/{$subscription->provider_id}", [
                'scheduled_change' => null,
            ]);

        if (! $response->successful()) {
            throw BillingException::failedAction(BillingProvider::Paddle, 'resume subscription', $response->body());
        }
    }
}
