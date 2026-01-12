<?php

namespace App\Domain\Billing\Adapters;

use App\Domain\Billing\Models\BillingCustomer;
use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\WebhookEvent;
use App\Domain\Billing\Services\BillingPlanService;
use App\Domain\Billing\Services\DiscountService;
use App\Domain\Organization\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class PaddleAdapter implements BillingProviderAdapter
{
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

        // Product sync
        if (str_contains($type, 'product') && config('saas.billing.sync_catalog_via_webhooks', true)) {
            $this->syncProduct($data, $type);
        }

        // Price sync
        if (str_contains($type, 'price') && config('saas.billing.sync_catalog_via_webhooks', true)) {
            $this->syncPrice($data, $type);
        }

        // Subscription handling
        if (str_contains($type, 'subscription')) {
            $this->syncSubscription($data, $type);
        }

        // Transaction/payment handling
        if (str_contains($type, 'transaction') || str_contains($type, 'payment')) {
            $this->syncOrder($data, $type);
        }
    }

    private function syncProduct(array $data, string $eventType): void
    {
        $productId = data_get($data, 'id');
        $name = data_get($data, 'name');

        if (!$productId) {
            return;
        }

        $customData = data_get($data, 'custom_data', []);
        $key = $customData['plan_key'] ?? $customData['product_key'] ?? Str::slug("paddle-{$productId}");

        Product::updateOrCreate(
            [
                'provider' => $this->provider(),
                'provider_id' => (string) $productId,
            ],
            [
                'key' => $key,
                'name' => $name ?? 'Paddle Product',
                'description' => data_get($data, 'description'),
                'is_active' => data_get($data, 'status', 'active') === 'active',
                'synced_at' => now(),
            ]
        );
    }

    private function syncPrice(array $data, string $eventType): void
    {
        $priceId = data_get($data, 'id');
        $productId = data_get($data, 'product_id');

        if (!$priceId) {
            return;
        }

        $product = Product::query()
            ->where('provider', $this->provider())
            ->where('provider_id', $productId)
            ->first();

        if (!$product) {
            return;
        }

        $customData = data_get($data, 'custom_data', []);
        $billingCycle = data_get($data, 'billing_cycle', []);
        $unitPrice = data_get($data, 'unit_price', []);

        $interval = $billingCycle['interval'] ?? 'once';
        $intervalCount = (int) ($billingCycle['frequency'] ?? 1);
        $key = $customData['price_key'] ?? ($interval === 'once' ? 'one_time' : ($intervalCount === 1 ? "{$interval}ly" : "every-{$intervalCount}-{$interval}"));

        Price::updateOrCreate(
            [
                'provider' => $this->provider(),
                'provider_id' => (string) $priceId,
            ],
            [
                'product_id' => $product->id,
                'key' => $key,
                'label' => data_get($data, 'description', ucfirst($key)),
                'interval' => $interval,
                'interval_count' => $intervalCount,
                'currency' => strtoupper($unitPrice['currency_code'] ?? 'USD'),
                'amount' => (int) ($unitPrice['amount'] ?? 0),
                'type' => 'flat',
                'is_active' => data_get($data, 'status', 'active') === 'active',
            ]
        );
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

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->patch("https://api.paddle.com/subscriptions/{$subscription->provider_id}", [
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
        $apiKey = config('services.paddle.api_key');

        if (!$apiKey) {
            throw new RuntimeException('Paddle API key is not configured.');
        }

        $planService = app(BillingPlanService::class);
        $priceId = $planService->providerPriceId($this->provider(), $planKey, $priceKey);

        if (!$priceId) {
            throw new RuntimeException("Paddle price id is missing for plan [{$planKey}] and price [{$priceKey}].");
        }

        $payload = [
            'items' => [
                [
                    'price_id' => $priceId,
                    'quantity' => max($quantity, 1),
                ],
            ],
            'custom_data' => [
                'team_id' => $team->id,
                'user_id' => $user->id,
                'plan_key' => $planKey,
                'price_key' => $priceKey,
            ],
            'checkout' => [
                'success_url' => $successUrl,
                'cancel_url' => $cancelUrl,
            ],
        ];

        $customerId = BillingCustomer::query()
            ->where('team_id', $team->id)
            ->where('provider', $this->provider())
            ->value('provider_id');

        if ($customerId) {
            $payload['customer_id'] = $customerId;
        } else {
            $payload['customer'] = [
                'email' => $user->email,
            ];
        }

        if ($discount) {
            $payload['discount_id'] = $discount->provider_id;
            $payload['custom_data']['discount_id'] = $discount->id;
            $payload['custom_data']['discount_code'] = $discount->code;
        }

        $response = Http::withToken($apiKey)
            ->acceptJson()
            ->post('https://api.paddle.com/transactions', $payload);

        if (!$response->successful()) {
            throw new RuntimeException('Paddle checkout failed: '.$response->body());
        }

        $url = data_get($response->json(), 'data.checkout.url')
            ?? data_get($response->json(), 'data.url')
            ?? data_get($response->json(), 'data.checkout_url');

        if (!$url) {
            throw new RuntimeException('Paddle checkout URL was not returned.');
        }

        return $url;
    }

    private function syncSubscription(array $data, string $eventType): void
    {
        $subscriptionId = data_get($data, 'id') ?? data_get($data, 'subscription_id');
        $teamId = $this->resolveTeamId($data);

        if (!$subscriptionId || !$teamId) {
            return;
        }

        $planKey = $this->resolvePlanKey($data) ?? 'unknown';
        $status = (string) (data_get($data, 'status') ?? data_get($data, 'state') ?? 'active');
        $quantity = (int) (data_get($data, 'quantity') ?? data_get($data, 'items.0.quantity') ?? 1);

        Subscription::query()->updateOrCreate(
            [
                'provider' => $this->provider(),
                'provider_id' => (string) $subscriptionId,
            ],
            [
                'team_id' => $teamId,
                'plan_key' => $planKey,
                'status' => $status,
                'quantity' => max($quantity, 1),
                'trial_ends_at' => $this->timestampToDateTime(data_get($data, 'trial_ends_at')),
                'renews_at' => $this->timestampToDateTime(data_get($data, 'next_billed_at')),
                'ends_at' => $this->timestampToDateTime(data_get($data, 'canceled_at')),
                'canceled_at' => $this->timestampToDateTime(data_get($data, 'canceled_at')),
                'metadata' => Arr::only($data, ['id', 'status', 'items', 'custom_data', 'management_urls', 'customer_id', 'customer']),
            ]
        );

        $this->syncBillingCustomer($teamId, data_get($data, 'customer_id') ?? data_get($data, 'customer.id'), data_get($data, 'customer_email'));

        $this->recordDiscountRedemption(
            $data,
            $teamId,
            $planKey,
            data_get($data, 'custom_data.price_key'),
            (string) $subscriptionId
        );
    }

    private function syncOrder(array $data, string $eventType): void
    {
        $orderId = data_get($data, 'id') ?? data_get($data, 'transaction_id');
        $teamId = $this->resolveTeamId($data);

        if (!$orderId || !$teamId) {
            return;
        }

        $status = (string) (data_get($data, 'status') ?? 'paid');
        $amount = (int) (data_get($data, 'amount') ?? data_get($data, 'amount_total') ?? 0);
        $currency = strtoupper((string) (data_get($data, 'currency_code') ?? data_get($data, 'currency') ?? 'USD'));

        $order = Order::query()->updateOrCreate(
            [
                'provider' => $this->provider(),
                'provider_id' => (string) $orderId,
            ],
            [
                'team_id' => $teamId,
                'plan_key' => $this->resolvePlanKey($data),
                'status' => $status,
                'amount' => $amount,
                'currency' => $currency,
                'paid_at' => now(),
                'metadata' => Arr::only($data, ['id', 'status', 'items', 'custom_data']),
            ]
        );

        $this->syncInvoiceFromOrder($data, $teamId, $order, $status, $amount, $currency);

        $this->recordDiscountRedemption(
            $data,
            $teamId,
            $order->plan_key,
            data_get($data, 'custom_data.price_key'),
            (string) $orderId
        );
    }

    private function resolveTeamId(array $data): ?int
    {
        $teamId = data_get($data, 'custom_data.team_id')
            ?? data_get($data, 'metadata.team_id')
            ?? data_get($data, 'team_id');

        return $teamId ? (int) $teamId : null;
    }

    private function resolvePlanKey(array $data): ?string
    {
        $planKey = data_get($data, 'custom_data.plan_key')
            ?? data_get($data, 'metadata.plan_key')
            ?? data_get($data, 'plan_key');

        if ($planKey) {
            return (string) $planKey;
        }

        $priceId = data_get($data, 'items.0.price_id') ?? data_get($data, 'price_id');

        if (!$priceId) {
            return null;
        }

        return app(BillingPlanService::class)
            ->resolvePlanKeyByProviderId($this->provider(), $priceId);
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

    private function syncBillingCustomer(int $teamId, ?string $providerId, ?string $email): void
    {
        $customer = BillingCustomer::query()
            ->where('team_id', $teamId)
            ->where('provider', $this->provider())
            ->first();

        $payload = [
            'team_id' => $teamId,
            'provider' => $this->provider(),
            'provider_id' => $providerId,
            'email' => $email,
        ];

        if ($customer) {
            $customer->update(array_filter($payload, fn ($value) => $value !== null));
            return;
        }

        BillingCustomer::query()->create($payload);
    }

    private function syncInvoiceFromOrder(
        array $data,
        int $teamId,
        Order $order,
        string $status,
        int $amount,
        string $currency
    ): void {
        $normalizedStatus = strtolower($status);
        $paid = in_array($normalizedStatus, ['paid', 'completed', 'settled'], true);

        Invoice::query()->updateOrCreate(
            [
                'provider' => $this->provider(),
                'provider_id' => (string) $order->provider_id,
            ],
            [
                'team_id' => $teamId,
                'order_id' => $order->id,
                'status' => $normalizedStatus,
                'amount_due' => $amount,
                'amount_paid' => $paid ? $amount : 0,
                'currency' => $currency,
                'issued_at' => $this->timestampToDateTime(data_get($data, 'created_at') ?? data_get($data, 'billed_at')),
                'paid_at' => $paid ? now() : null,
                'hosted_invoice_url' => data_get($data, 'invoice_url') ?? data_get($data, 'invoice_pdf'),
                'invoice_pdf' => data_get($data, 'invoice_pdf'),
                'metadata' => Arr::only($data, ['id', 'status', 'items', 'custom_data', 'invoice_url', 'invoice_pdf']),
            ]
        );
    }

    private function recordDiscountRedemption(
        array $data,
        int $teamId,
        ?string $planKey,
        ?string $priceKey,
        string $providerId
    ): void {
        $customData = data_get($data, 'custom_data', []);
        $discountId = $customData['discount_id'] ?? data_get($data, 'metadata.discount_id');
        $discountCode = $customData['discount_code'] ?? data_get($data, 'metadata.discount_code');

        if (!$discountId && !$discountCode) {
            return;
        }

        $discount = null;

        if ($discountId) {
            $discount = Discount::query()->find($discountId);
        } elseif ($discountCode) {
            $discount = Discount::query()
                ->where('provider', $this->provider())
                ->where('code', strtoupper((string) $discountCode))
                ->first();
        }

        if (!$discount) {
            return;
        }

        $team = Team::find($teamId);

        if (!$team) {
            return;
        }

        $userId = $customData['user_id'] ?? null;
        $user = $userId ? User::find($userId) : null;

        app(DiscountService::class)->recordRedemption(
            $discount,
            $team,
            $user,
            $this->provider(),
            $providerId,
            $planKey,
            $priceKey,
            [
                'source' => 'paddle_webhook',
            ]
        );
    }

    private function timestampToDateTime(?string $timestamp): ?\Illuminate\Support\Carbon
    {
        if (!$timestamp) {
            return null;
        }

        if (is_numeric($timestamp)) {
            return now()->setTimestamp((int) $timestamp);
        }

        return \Carbon\Carbon::parse($timestamp);
    }
}
