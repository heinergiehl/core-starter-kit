<?php

namespace App\Domain\Billing\Adapters;

use App\Domain\Billing\Models\BillingCustomer;
use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\PriceProviderMapping;
use App\Domain\Billing\Models\Product;
use App\Domain\Billing\Models\ProductProviderMapping;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\WebhookEvent;
use App\Domain\Billing\Services\BillingPlanService;
use App\Domain\Billing\Services\DiscountService;
use App\Domain\Organization\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use RuntimeException;

class LemonSqueezyAdapter implements BillingProviderAdapter
{
    public function provider(): string
    {
        return 'lemonsqueezy';
    }

    public function parseWebhook(Request $request): array
    {
        $payload = $request->getContent();
        $signature = $request->header('X-Signature');
        $secret = config('services.lemonsqueezy.webhook_secret');

        // Verify signature in production
        if (!app()->environment(['local', 'testing'])) {
            if (!$secret) {
                throw new RuntimeException('Lemon Squeezy webhook secret is not configured.');
            }

            if (!$signature) {
                throw new RuntimeException('Lemon Squeezy webhook signature header is missing.');
            }

            $expectedSignature = hash_hmac('sha256', $payload, $secret);

            if (!hash_equals($expectedSignature, $signature)) {
                throw new RuntimeException('Invalid Lemon Squeezy webhook signature.');
            }
        }

        $data = json_decode($payload, true) ?? [];
        $eventId = (string) ($data['meta']['webhook_id'] ?? $data['id'] ?? '');

        return [
            'id' => $eventId !== '' ? $eventId : (string) ($data['meta']['event_name'] ?? Str::uuid()),
            'type' => $data['meta']['event_name'] ?? null,
            'payload' => $data,
        ];
    }

    public function processEvent(WebhookEvent $event): void
    {
        $payload = $event->payload ?? [];
        $type = data_get($payload, 'meta.event_name') ?? $payload['event_type'] ?? $payload['type'] ?? $event->type;
        $attributes = data_get($payload, 'data.attributes', []);

        if (!$type || !$attributes) {
            return;
        }

        // Product sync
        if (str_contains($type, 'product')) {
            $this->syncProduct($payload, $attributes, $type);
        }

        // Variant (price) sync
        if (str_contains($type, 'variant')) {
            $this->syncVariant($payload, $attributes, $type);
        }

        // Subscription handling
        if (str_contains($type, 'subscription')) {
            $this->syncSubscription($payload, $attributes, $type);
        }

        // Order/payment handling
        if (str_contains($type, 'order') || str_contains($type, 'payment')) {
            $this->syncOrder($payload, $attributes, $type);
        }
    }

    private function syncProduct(array $payload, array $attributes, string $eventType): void
    {
        $productId = data_get($payload, 'data.id');
        $name = $attributes['name'] ?? null;

        if (!$productId) {
            return;
        }

        $customData = $attributes['custom_data'] ?? [];
        $key = $customData['plan_key'] ?? $customData['product_key'] ?? Str::slug("ls-{$productId}");

        $mapping = ProductProviderMapping::where('provider', $this->provider())
            ->where('provider_id', (string) $productId)
            ->first();

        if ($mapping && !$mapping->product && !config('saas.billing.allow_import_deleted', false)) {
            return;
        }

        $status = $attributes['status'] ?? 'published';
        if (!$mapping && $status !== 'published') {
            return;
        }

        $productData = [
            'key' => $key,
            'name' => $name ?? 'LemonSqueezy Product',
            'description' => $attributes['description'] ?? null,
            'is_active' => $status === 'published',
            'synced_at' => now(),
        ];

        if ($mapping) {
            $mapping->product->update($productData);
            return;
        }

        $product = Product::create($productData);

        ProductProviderMapping::create([
            'product_id' => $product->id,
            'provider' => $this->provider(),
            'provider_id' => (string) $productId,
        ]);
    }

    private function syncVariant(array $payload, array $attributes, string $eventType): void
    {
        $variantId = data_get($payload, 'data.id');
        $productId = data_get($payload, 'data.relationships.product.data.id');

        if (!$variantId) {
            return;
        }

        $product = Product::query()
            ->whereHas('providerMappings', function ($q) use ($productId) {
                $q->where('provider', $this->provider())
                  ->where('provider_id', $productId);
            })
            ->first();

        if (!$product) {
            return;
        }

        $customData = $attributes['custom_data'] ?? [];
        $isSubscription = $attributes['is_subscription'] ?? false;
        $interval = $isSubscription ? ($attributes['interval'] ?? 'month') : 'once';
        $intervalCount = (int) ($attributes['interval_count'] ?? 1);
        $key = $customData['price_key'] ?? ($interval === 'once' ? 'one_time' : ($intervalCount === 1 ? "{$interval}ly" : "every-{$intervalCount}-{$interval}"));

        $mapping = PriceProviderMapping::where('provider', $this->provider())
            ->where('provider_id', (string) $variantId)
            ->first();

        if ($mapping && !$mapping->price && !config('saas.billing.allow_import_deleted', false)) {
            return;
        }

        $status = $attributes['status'] ?? 'published';
        if (!$mapping && $status !== 'published') {
            return;
        }

        $priceData = [
            'product_id' => $product->id,
            'key' => $key,
            'label' => $attributes['name'] ?? ucfirst($key),
            'interval' => $interval,
            'interval_count' => $intervalCount,
            'currency' => 'USD',
            'amount' => (int) ($attributes['price'] ?? 0),
            'type' => 'flat',
            'is_active' => $status === 'published',
        ];

        if ($mapping) {
            $mapping->price->update($priceData);
            return;
        }

        $price = Price::create($priceData);

        PriceProviderMapping::create([
            'price_id' => $price->id,
            'provider' => $this->provider(),
            'provider_id' => (string) $variantId,
        ]);
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

        $apiKey = config('services.lemonsqueezy.api_key');

        if (!$apiKey) {
            throw new RuntimeException('Lemon Squeezy API key is not configured.');
        }

        $response = Http::withToken($apiKey)
            ->withHeaders([
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json',
            ])
            ->withBody(json_encode([
                'data' => [
                    'type' => 'subscriptions',
                    'id' => (string) $subscription->provider_id,
                    'attributes' => [
                        'quantity' => max($quantity, 1),
                    ],
                ],
            ]), 'application/vnd.api+json')
            ->patch("https://api.lemonsqueezy.com/v1/subscriptions/{$subscription->provider_id}");

        if (!$response->successful()) {
            throw new RuntimeException('Lemon Squeezy seat sync failed: ' . $response->body());
        }
        if (!$response->successful()) {
            throw new RuntimeException('Lemon Squeezy seat sync failed: ' . $response->body());
        }
    }

    public function createDiscount(Discount $discount): string
    {
        $apiKey = config('services.lemonsqueezy.api_key');
        $storeId = config('services.lemonsqueezy.store_id');

        if (!$apiKey || !$storeId) {
            throw new RuntimeException('Lemon Squeezy API key or store id is not configured.');
        }
        
        $attributes = [
            'name' => $discount->name,
            'code' => $discount->code,
            'amount' => (int) $discount->amount,
            'amount_type' => $discount->type === 'percent' ? 'percent' : 'fixed',
        ];
        
        if ($discount->max_redemptions) {
            $attributes['is_limited_redemptions'] = true;
            $attributes['max_redemptions'] = $discount->max_redemptions;
        }
        
        if ($discount->starts_at) {
            $attributes['starts_at'] = $discount->starts_at->toIso8601String();
        }
        
        if ($discount->ends_at) {
            $attributes['expires_at'] = $discount->ends_at->toIso8601String();
        }

        $payload = [
            'data' => [
                'type' => 'discounts',
                'attributes' => $attributes,
                'relationships' => [
                    'store' => [
                        'data' => [
                            'type' => 'stores',
                            'id' => (string) $storeId,
                        ],
                    ],
                ],
            ],
        ];

        $response = Http::withToken($apiKey)
            ->withHeaders([
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json',
            ])
            ->withBody(json_encode($payload), 'application/vnd.api+json')
            ->post('https://api.lemonsqueezy.com/v1/discounts');

        if (!$response->successful()) {
            throw new RuntimeException('Lemon Squeezy discount creation failed: ' . $response->body());
        }

        return $response->json('data.id');
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
        $apiKey = config('services.lemonsqueezy.api_key');
        $storeId = config('services.lemonsqueezy.store_id');

        if (!$apiKey || !$storeId) {
            throw new RuntimeException('Lemon Squeezy API key or store id is not configured.');
        }

        $planService = app(BillingPlanService::class);
        $variantId = $planService->providerPriceId($this->provider(), $planKey, $priceKey);

        if (!$variantId) {
            throw new RuntimeException("Lemon Squeezy variant id is missing for plan [{$planKey}] and price [{$priceKey}].");
        }

        $payload = [
            'data' => [
                'type' => 'checkouts',
                'attributes' => [
                    'checkout_data' => [
                        'email' => $user->email,
                        'custom' => [
                            'team_id' => (string) $team->id,
                            'user_id' => (string) $user->id,
                            'plan_key' => $planKey,
                            'price_key' => $priceKey,
                        ],
                        'variant_quantities' => [
                            [
                                'variant_id' => (int) $variantId,
                                'quantity' => max($quantity, 1),
                            ],
                        ],
                    ],
                    'product_options' => [
                        'redirect_url' => $successUrl,
                    ],
                ],
                'relationships' => [
                    'store' => [
                        'data' => [
                            'type' => 'stores',
                            'id' => (string) $storeId,
                        ],
                    ],
                    'variant' => [
                        'data' => [
                            'type' => 'variants',
                            'id' => (string) $variantId,
                        ],
                    ],
                ],
            ],
        ];

        if ($discount) {
            $payload['data']['attributes']['checkout_data']['discount_code'] = $discount->code;
            $payload['data']['attributes']['checkout_data']['custom']['discount_id'] = (string) $discount->id;
            $payload['data']['attributes']['checkout_data']['custom']['discount_code'] = $discount->code;

            if ($discount->provider_id) {
                $payload['data']['attributes']['discount_id'] = $discount->provider_id;
            }
        }

        $response = $this->postCheckout($apiKey, $payload);

        if (!$response->successful()) {
            throw new RuntimeException('Lemon Squeezy checkout failed: ' . $response->body());
        }

        $url = data_get($response->json(), 'data.attributes.url');

        if (!$url) {
            throw new RuntimeException('Lemon Squeezy checkout URL was not returned.');
        }

        return $url;
    }

    private function syncSubscription(array $payload, array $attributes, string $eventType): void
    {
        $subscriptionId = data_get($payload, 'data.id') ?? data_get($attributes, 'subscription_id');
        $teamId = $this->resolveTeamId($payload, $attributes);

        if (!$subscriptionId || !$teamId) {
            return;
        }

        $planKey = $this->resolvePlanKey($payload, $attributes) ?? 'unknown';
        $status = (string) (data_get($attributes, 'status') ?? 'active');
        $quantity = (int) (data_get($attributes, 'quantity') ?? 1);

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
                'trial_ends_at' => $this->timestampToDateTime(data_get($attributes, 'trial_ends_at')),
                'renews_at' => $this->timestampToDateTime(data_get($attributes, 'renews_at')),
                'ends_at' => $this->timestampToDateTime(data_get($attributes, 'ends_at')),
                'canceled_at' => $this->timestampToDateTime(data_get($attributes, 'cancelled_at')),
                'metadata' => Arr::only($attributes, ['status', 'quantity', 'urls', 'price_id', 'variant_id']),
            ]
        );

        $this->syncBillingCustomer($teamId, data_get($attributes, 'customer_id'), data_get($attributes, 'user_email'));

        $this->recordDiscountRedemption(
            $payload,
            $attributes,
            $teamId,
            $planKey,
            data_get($payload, 'meta.custom_data.price_key')
        );
    }

    private function syncOrder(array $payload, array $attributes, string $eventType): void
    {
        $orderId = data_get($payload, 'data.id') ?? data_get($attributes, 'order_id');
        $teamId = $this->resolveTeamId($payload, $attributes);

        if (!$orderId || !$teamId) {
            return;
        }

        $status = (string) (data_get($attributes, 'status') ?? 'paid');
        $amount = (int) (data_get($attributes, 'total') ?? data_get($attributes, 'subtotal') ?? 0);
        $currency = strtoupper((string) (data_get($attributes, 'currency') ?? 'USD'));

        $order = Order::query()->updateOrCreate(
            [
                'provider' => $this->provider(),
                'provider_id' => (string) $orderId,
            ],
            [
                'team_id' => $teamId,
                'plan_key' => $this->resolvePlanKey($payload, $attributes),
                'status' => $status,
                'amount' => $amount,
                'currency' => $currency,
                'paid_at' => now(),
                'metadata' => Arr::only($attributes, ['status', 'total', 'currency', 'price_id', 'discount_code']),
            ]
        );

        $this->syncInvoiceFromOrder($payload, $attributes, $teamId, $order, $status, $amount, $currency);

        $this->recordDiscountRedemption(
            $payload,
            $attributes,
            $teamId,
            $order->plan_key,
            data_get($payload, 'meta.custom_data.price_key')
        );
    }

    private function resolveTeamId(array $payload, array $attributes): ?int
    {
        $teamId = data_get($payload, 'meta.custom_data.team_id')
            ?? data_get($payload, 'meta.custom.team_id')
            ?? data_get($attributes, 'team_id');

        return $teamId ? (int) $teamId : null;
    }

    private function resolvePlanKey(array $payload, array $attributes): ?string
    {
        $planKey = data_get($payload, 'meta.custom_data.plan_key')
            ?? data_get($payload, 'meta.custom.plan_key')
            ?? data_get($attributes, 'plan_key');

        if ($planKey) {
            return (string) $planKey;
        }

        $priceId = data_get($attributes, 'price_id');

        if (!$priceId) {
            return null;
        }

        return app(BillingPlanService::class)
            ->resolvePlanKeyByProviderId($this->provider(), $priceId);
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
            $customer->update(array_filter($payload, fn($value) => $value !== null));
            return;
        }

        BillingCustomer::query()->create($payload);
    }

    private function postCheckout(string $apiKey, array $payload): \Illuminate\Http\Client\Response
    {
        return Http::withToken($apiKey)
            ->withHeaders([
                'Accept' => 'application/vnd.api+json',
                'Content-Type' => 'application/vnd.api+json',
            ])
            ->withBody(json_encode($payload), 'application/vnd.api+json')
            ->post('https://api.lemonsqueezy.com/v1/checkouts');
    }

    private function syncInvoiceFromOrder(
        array $payload,
        array $attributes,
        int $teamId,
        Order $order,
        string $status,
        int $amount,
        string $currency
    ): void {
        $normalizedStatus = strtolower($status);
        $paid = in_array($normalizedStatus, ['paid', 'completed', 'settled'], true);
        $urls = data_get($attributes, 'urls', []);

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
                'issued_at' => $this->timestampToDateTime(data_get($attributes, 'created_at')),
                'paid_at' => $paid ? now() : null,
                'hosted_invoice_url' => $urls['invoice_url'] ?? $urls['receipt'] ?? null,
                'invoice_pdf' => $urls['invoice_pdf'] ?? $urls['receipt'] ?? null,
                'metadata' => Arr::only($attributes, ['status', 'total', 'currency', 'price_id', 'discount_code', 'urls']),
            ]
        );
    }

    private function recordDiscountRedemption(
        array $payload,
        array $attributes,
        int $teamId,
        ?string $planKey,
        ?string $priceKey
    ): void {
        $customData = data_get($payload, 'meta.custom_data', [])
            ?: data_get($payload, 'meta.custom', []);
        $discountId = $customData['discount_id'] ?? data_get($attributes, 'discount_id');
        $discountCode = $customData['discount_code'] ?? data_get($attributes, 'discount_code');

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
        $providerId = (string) (data_get($attributes, 'order_id') ?? data_get($payload, 'data.id') ?? '');

        if (!$providerId) {
            return;
        }

        app(DiscountService::class)->recordRedemption(
            $discount,
            $team,
            $user,
            $this->provider(),
            $providerId,
            $planKey,
            $priceKey,
            [
                'source' => 'lemonsqueezy_webhook',
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

        return \Illuminate\Support\Carbon::parse($timestamp);
    }
    public function archiveProduct(string $providerId): void
    {
        $apiKey = config('services.lemonsqueezy.api_key');

        if (!$apiKey) {
            throw new RuntimeException('Lemon Squeezy API key is missing.');
        }

        // Lemon Squeezy uses DELETE to remove/archive
        $response = Http::withToken($apiKey)
            ->accept('application/vnd.api+json')
            ->delete("https://api.lemonsqueezy.com/v1/products/{$providerId}");

        if ($response->status() === 404) {
            return;
        }

        if (!$response->successful()) {
            throw new RuntimeException('Lemon Squeezy archive product failed: ' . $response->body());
        }
    }

    public function archivePrice(string $providerId): void
    {
        $apiKey = config('services.lemonsqueezy.api_key');

        if (!$apiKey) {
            throw new RuntimeException('Lemon Squeezy API key is missing.');
        }

        // Lemon Squeezy "prices" are Variants
        $response = Http::withToken($apiKey)
            ->accept('application/vnd.api+json')
            ->delete("https://api.lemonsqueezy.com/v1/variants/{$providerId}");

        if ($response->status() === 404) {
            return;
        }

        if (!$response->successful()) {
            throw new RuntimeException('Lemon Squeezy archive price failed: ' . $response->body());
        }
    }
}
