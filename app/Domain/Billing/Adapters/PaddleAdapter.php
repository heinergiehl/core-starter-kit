<?php

namespace App\Domain\Billing\Adapters;

use App\Domain\Billing\Adapters\Paddle\Concerns\ResolvesPaddleData;
use App\Domain\Billing\Adapters\Paddle\Handlers\PaddleOrderHandler;
use App\Domain\Billing\Adapters\Paddle\Handlers\PaddlePriceHandler;
use App\Domain\Billing\Adapters\Paddle\Handlers\PaddleProductHandler;
use App\Domain\Billing\Adapters\Paddle\Handlers\PaddleSubscriptionHandler;
use App\Domain\Billing\Contracts\PaddleWebhookHandler;
use App\Domain\Billing\Models\BillingCustomer;
use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\WebhookEvent;
use App\Domain\Billing\Services\BillingPlanService;
use App\Domain\Organization\Models\Team;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Paddle billing provider adapter.
 *
 * This adapter handles all Paddle-related billing operations including:
 * - Webhook parsing and processing
 * - Checkout session creation
 * - Seat quantity synchronization
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
        if (!app()->environment(['local', 'testing'])) {
            if (!$secret) {
                throw new RuntimeException('Paddle webhook secret is not configured.');
            }

            if (!$signature) {
                throw new RuntimeException('Paddle webhook signature header is missing.');
            }

            if (!$this->verifyPaddleSignature($payload, $signature, $secret)) {
                throw new RuntimeException('Invalid Paddle webhook signature.');
            }
        }

        $data = json_decode($payload, true) ?? [];

        return [
            'id' => (string) ($data['event_id'] ?? $data['id'] ?? Str::uuid()),
            'type' => $data['event_type'] ?? $data['type'] ?? null,
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

        if (!$timestamp || !$hash) {
            return false;
        }

        // Build signed payload: timestamp:payload
        $signedPayload = $timestamp . ':' . $payload;
        $expectedHash = hash_hmac('sha256', $signedPayload, $secret);

        return hash_equals($expectedHash, $hash);
    }

    public function processEvent(WebhookEvent $event): void
    {
        $payload = $event->payload ?? [];
        $type = $payload['event_type'] ?? $payload['type'] ?? $event->type;
        $data = $payload['data'] ?? $payload;

        if (!$type || !$data) {
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
            new PaddleProductHandler(),
            new PaddlePriceHandler(),
            new PaddleSubscriptionHandler(),
            new PaddleOrderHandler(),
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


    public function syncSeatQuantity(Team $team, int $quantity): void
    {
        $subscription = Subscription::query()
            ->where('team_id', $team->id)
            ->where('provider', $this->provider())
            ->whereIn('status', ['active', 'trialing'])
            ->latest('id')
            ->first();

        if (!$subscription) {
            return;
        }

        $apiKey = config('services.paddle.api_key');

        if (!$apiKey) {
            throw new RuntimeException('Paddle API key is not configured.');
        }

        $priceId = $this->resolvePriceId($subscription);

        if (!$priceId) {
            throw new RuntimeException('Paddle price id is missing for seat sync.');
        }

        $response = $this->paddleRequest($apiKey)
            ->patch("/subscriptions/{$subscription->provider_id}", [
                'items' => [
                    [
                        'price_id' => $priceId,
                        'quantity' => max($quantity, 1),
                    ],
                ],
            ]);

        if (!$response->successful()) {
            throw new RuntimeException('Paddle seat sync failed: '.$response->body());
        }
    }

    public function createCheckout(
        Team $team,
        User $user,
        string $planKey,
        string $priceKey,
        int $quantity,
        string $successUrl,
        string $cancelUrl,
        ?Discount $discount = null
    ): string {
        $payload = $this->buildTransactionPayload(
            $team,
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

        if (!$url) {
            throw new RuntimeException('Paddle checkout URL was not returned.');
        }

        return $url;
    }

    public function createTransactionId(
        ?Team $team,
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
            $team,
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

        if (!$transactionId) {
            throw new RuntimeException('Paddle transaction id was not returned.');
        }

        return (string) $transactionId;
    }

    /**
     * @return array<string, mixed>
     */
    private function buildTransactionPayload(
        ?Team $team,
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

        if (!$priceId) {
            throw new RuntimeException("Paddle price id is missing for plan [{$planKey}] and price [{$priceKey}].");
        }

        $customData = [
            'team_id' => $team?->id,
            'user_id' => $user?->id,
            'plan_key' => $planKey,
            'price_key' => $priceKey,
        ];

        if ($discount) {
            $customData['discount_id'] = $discount->id;
            $customData['discount_code'] = $discount->code;
        }

        if (!empty($extraCustomData)) {
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
        if ($team) {
            $customerId = BillingCustomer::query()
                ->where('team_id', $team->id)
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
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function createTransaction(array $payload): array
    {
        $apiKey = config('services.paddle.api_key');

        if (!$apiKey) {
            throw new RuntimeException('Paddle API key is not configured.');
        }

        $response = $this->paddleRequest($apiKey)
            ->post('/transactions', $payload);

        if (!$response->successful()) {
            throw new RuntimeException('Paddle transaction failed: '.$response->body());
        }

        return $response->json('data') ?? [];
    }

    public function createDiscount(Discount $discount): string
    {
        $apiKey = config('services.paddle.api_key');
        if (!$apiKey) {
            throw new RuntimeException('Paddle API key is not configured.');
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

        if (!$response->successful()) {
            throw new RuntimeException('Paddle discount creation failed: ' . $response->body());
        }

        return $response->json('data.id');
    }



    private function resolvePriceId(Subscription $subscription): ?string
    {
        $metadata = $subscription->metadata ?? [];
        $priceId = data_get($metadata, 'items.0.price_id')
            ?? data_get($metadata, 'items.0.price.id')
            ?? data_get($metadata, 'price_id');

        if ($priceId) {
            return (string) $priceId;
        }

        $planKey = $subscription->plan_key;

        if (!$planKey) {
            return null;
        }

        $plans = app(BillingPlanService::class)->plansForProvider($this->provider());
        $plan = collect($plans)->firstWhere('key', $planKey);
        $prices = $plan['prices'] ?? [];

        foreach ($prices as $price) {
            if (!empty($price['provider_id'])) {
                return (string) $price['provider_id'];
            }
        }

        return null;
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
            if (!$response) {
                return true;
            }

            return $response->serverError()
                || $response->status() === 429
                || $response->status() === 408;
        }

        return true;
    }
}
