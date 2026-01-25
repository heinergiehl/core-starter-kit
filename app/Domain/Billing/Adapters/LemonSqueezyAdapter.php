<?php

namespace App\Domain\Billing\Adapters;

use App\Domain\Billing\Exceptions\BillingException;
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
use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class LemonSqueezyAdapter implements BillingProviderAdapter
{
    public function __construct(
        protected \App\Domain\Billing\Services\SubscriptionService $subscriptionService,
        protected \App\Domain\Billing\Services\DiscountService $discountService,
        protected \App\Domain\Billing\Services\BillingPlanService $planService,
        protected \App\Domain\Billing\Services\CheckoutService $checkoutService
    ) {}

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
        if (! app()->environment(['local', 'testing'])) {
            if (! $secret) {
                throw BillingException::missingConfiguration('Lemon Squeezy', 'webhook secret');
            }

            if (! $signature) {
                throw BillingException::webhookValidationFailed('Lemon Squeezy', 'signature header is missing');
            }

            $expectedSignature = hash_hmac('sha256', $payload, $secret);

            if (! hash_equals($expectedSignature, $signature)) {
                throw BillingException::webhookValidationFailed('Lemon Squeezy', 'invalid signature');
            }
        }

        $data = json_decode($payload, true) ?? [];
        $eventId = (string) ($data['meta']['webhook_id'] ?? $data['id'] ?? '');
        if ($eventId === '') {
            $eventId = hash('sha256', $payload);
        }

        return [
            'id' => $eventId,
            'type' => $data['meta']['event_name'] ?? null,
            'payload' => $data,
        ];
    }

    public function processEvent(WebhookEvent $event): void
    {
        $payload = $event->payload ?? [];
        $type = data_get($payload, 'meta.event_name') ?? $payload['event_type'] ?? $payload['type'] ?? $event->type;
        $attributes = data_get($payload, 'data.attributes', []);

        if (! $type || ! $attributes) {
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

        if (! $productId) {
            return;
        }

        $customData = $attributes['custom_data'] ?? [];
        $key = $customData['plan_key'] ?? $customData['product_key'] ?? Str::slug("ls-{$productId}");

        $mapping = ProductProviderMapping::where('provider', $this->provider())
            ->where('provider_id', (string) $productId)
            ->first();

        if ($mapping && ! $mapping->product && ! config('saas.billing.allow_import_deleted', false)) {
            return;
        }

        $status = $attributes['status'] ?? 'published';
        if (! $mapping && $status !== 'published') {
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

        if (! $variantId) {
            return;
        }

        $product = Product::query()
            ->whereHas('providerMappings', function ($q) use ($productId) {
                $q->where('provider', $this->provider())
                    ->where('provider_id', $productId);
            })
            ->first();

        if (! $product) {
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

        if ($mapping && ! $mapping->price && ! config('saas.billing.allow_import_deleted', false)) {
            return;
        }

        $status = $attributes['status'] ?? 'published';
        if (! $mapping && $status !== 'published') {
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
            'type' => $isSubscription ? \App\Enums\PriceType::Recurring : \App\Enums\PriceType::OneTime,
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

    public function createDiscount(Discount $discount): string
    {
        $apiKey = config('services.lemonsqueezy.api_key');
        $storeId = config('services.lemonsqueezy.store_id');

        if (! $apiKey || ! $storeId) {
            throw BillingException::missingConfiguration('Lemon Squeezy', 'api_key or store_id');
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

        $response = $this->lemonSqueezyRequest($apiKey)
            ->withBody(json_encode($payload), 'application/vnd.api+json')
            ->post('/discounts');

        if (! $response->successful()) {
            throw BillingException::failedAction('Lemon Squeezy', 'create discount', $response->body());
        }

        return $response->json('data.id');
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
        $apiKey = config('services.lemonsqueezy.api_key');
        $storeId = config('services.lemonsqueezy.store_id');

        if (! $apiKey || ! $storeId) {
            throw BillingException::missingConfiguration('Lemon Squeezy', 'api_key or store_id');
        }

        $variantId = $this->planService->providerPriceId($this->provider(), $planKey, $priceKey);

        if (! $variantId) {
            throw BillingException::missingPriceId('Lemon Squeezy', $planKey, $priceKey);
        }

        $payload = [
            'data' => [
                'type' => 'checkouts',
                'attributes' => [
                    'checkout_data' => [
                        'email' => $user->email,
                        'custom' => [
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

        if (! $response->successful()) {
            throw BillingException::checkoutFailed('Lemon Squeezy', $response->body());
        }

        $url = data_get($response->json(), 'data.attributes.url');

        if (! $url) {
            throw BillingException::checkoutFailed('Lemon Squeezy', 'checkout URL was not returned');
        }

        return $url;
    }

    private function syncSubscription(array $payload, array $attributes, string $eventType): void
    {
        $subscriptionId = data_get($payload, 'data.id') ?? data_get($attributes, 'subscription_id');
        $userId = $this->resolveUserId($payload, $attributes);

        if (! $subscriptionId || ! $userId) {
            return;
        }

        if (! $subscriptionId || ! $userId) {
            return;
        }

        $planKey = $this->resolvePlanKey($payload, $attributes) ?? 'unknown';
        $status = (string) (data_get($attributes, 'status') ?? 'active');
        $quantity = (int) (data_get($attributes, 'quantity') ?? 1);

        $subscription = $this->subscriptionService->syncFromProvider(
            provider: $this->provider(),
            providerId: (string) $subscriptionId,
            userId: $userId,
            planKey: $planKey,
            status: $status,
            quantity: max($quantity, 1),
            dates: [
                'trial_ends_at' => $this->timestampToDateTime(data_get($attributes, 'trial_ends_at')),
                'renews_at' => $this->timestampToDateTime(data_get($attributes, 'renews_at')),
                'ends_at' => $this->timestampToDateTime(data_get($attributes, 'ends_at')),
                'canceled_at' => $this->timestampToDateTime(data_get($attributes, 'cancelled_at')),
            ],
            metadata: Arr::only($attributes, ['status', 'quantity', 'urls', 'price_id', 'variant_id'])
        );

        $this->syncBillingCustomer($userId, data_get($attributes, 'customer_id'), data_get($attributes, 'user_email'));

        $this->recordDiscountRedemption(
            $payload,
            $attributes,
            $userId,
            $planKey,
            data_get($payload, 'meta.custom_data.price_key')
        );

        if ($status === 'active') {
            $this->checkoutService->verifyUserAfterPayment($userId);
        }
    }

    private function syncOrder(array $payload, array $attributes, string $eventType): void
    {
        $orderId = data_get($payload, 'data.id') ?? data_get($attributes, 'order_id');
        $userId = $this->resolveUserId($payload, $attributes);

        if (! $orderId || ! $userId) {
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
                'user_id' => $userId,
                'plan_key' => $this->resolvePlanKey($payload, $attributes),
                'status' => $status,
                'amount' => $amount,
                'currency' => $currency,
                'paid_at' => now(),
                'metadata' => Arr::only($attributes, ['status', 'total', 'currency', 'price_id', 'discount_code']),
            ]
        );

        $invoice = $this->syncInvoiceFromOrder($payload, $attributes, $userId, $order, $status, $amount, $currency);

        $this->maybeNotifyPaymentFailed($order, $invoice, $status, $eventType);

        $this->recordDiscountRedemption(
            $payload,
            $attributes,
            $userId,
            $order->plan_key,
            data_get($payload, 'meta.custom_data.price_key')
        );

        if (in_array(strtolower($status), ['paid', 'completed', 'settled'], true)) {
            $this->checkoutService->verifyUserAfterPayment($userId);
        }
    }

    private function resolveUserId(array $payload, array $attributes): ?int
    {
        $userId = data_get($payload, 'meta.custom_data.user_id')
            ?? data_get($payload, 'meta.custom.user_id')
            ?? data_get($attributes, 'user_id');

        return $userId ? (int) $userId : null;
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

        if (! $priceId) {
            return null;
        }

        return $this->planService->resolvePlanKeyByProviderId($this->provider(), $priceId);
    }

    private function syncBillingCustomer(int $userId, ?string $providerId, ?string $email): void
    {
        $customer = BillingCustomer::query()
            ->where('user_id', $userId)
            ->where('provider', $this->provider())
            ->first();

        $payload = [
            'user_id' => $userId,
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

    private function postCheckout(string $apiKey, array $payload): \Illuminate\Http\Client\Response
    {
        return $this->lemonSqueezyRequest($apiKey)
            ->withBody(json_encode($payload), 'application/vnd.api+json')
            ->post('/checkouts');
    }

    private function lemonSqueezyRequest(string $apiKey): PendingRequest
    {
        $timeout = (int) config('saas.billing.provider_api.timeouts.lemonsqueezy', 15);
        $connectTimeout = (int) config('saas.billing.provider_api.connect_timeouts.lemonsqueezy', 5);
        $retries = (int) config('saas.billing.provider_api.retries.lemonsqueezy', 2);
        $retryDelay = (int) config('saas.billing.provider_api.retry_delay_ms', 500);

        return Http::withToken($apiKey)
            ->accept('application/vnd.api+json')
            ->withHeaders([
                'Content-Type' => 'application/vnd.api+json',
            ])
            ->baseUrl('https://api.lemonsqueezy.com/v1')
            ->timeout($timeout)
            ->connectTimeout($connectTimeout)
            ->retry(
                $retries,
                $retryDelay,
                fn ($exception, $request = null, $method = null): bool => $this->shouldRetryProviderRequest($exception),
                false
            );
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

    private function syncInvoiceFromOrder(
        array $payload,
        array $attributes,
        int $userId,
        Order $order,
        string $status,
        int $amount,
        string $currency
    ): Invoice {
        $normalizedStatus = strtolower($status);
        $paid = in_array($normalizedStatus, ['paid', 'completed', 'settled'], true);
        $urls = data_get($attributes, 'urls', []);

        $invoice = Invoice::query()->updateOrCreate(
            [
                'provider' => $this->provider(),
                'provider_id' => (string) $order->provider_id,
            ],
            [
                'user_id' => $userId,
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

        return $invoice;
    }

    private function maybeNotifyPaymentFailed(
        Order $order,
        Invoice $invoice,
        string $status,
        string $eventType
    ): void {
        if ($invoice->payment_failed_email_sent_at) {
            return;
        }

        $normalizedStatus = strtolower($status);
        $failedEvent = str_contains($eventType, 'payment_failed');
        $failedStatus = in_array($normalizedStatus, ['failed', 'unpaid', 'past_due'], true);

        if (! $failedEvent && ! $failedStatus) {
            return;
        }

        $owner = $order->user;

        if (! $owner) {
            return;
        }

        $owner->notify(new PaymentFailedNotification(
            planName: $this->resolvePlanName($order->plan_key),
            amount: $order->amount,
            currency: $order->currency,
            failureReason: null,
        ));

        $invoice->forceFill(['payment_failed_email_sent_at' => now()])->save();
    }

    private function recordDiscountRedemption(
        array $payload,
        array $attributes,
        int $userId,
        ?string $planKey,
        ?string $priceKey
    ): void {
        $customData = data_get($payload, 'meta.custom_data', [])
            ?: data_get($payload, 'meta.custom', []);
        $discountId = $customData['discount_id'] ?? data_get($attributes, 'discount_id');
        $discountCode = $customData['discount_code'] ?? data_get($attributes, 'discount_code');

        if (! $discountId && ! $discountCode) {
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

        if (! $discount) {
            return;
        }

        $user = User::find($userId);
        $providerId = (string) (data_get($attributes, 'order_id') ?? data_get($payload, 'data.id') ?? '');

        if (! $providerId) {
            return;
        }

        $this->discountService->recordRedemption(
            $discount,
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

    private function resolvePlanName(?string $planKey): string
    {
        if (! $planKey) {
            return 'subscription';
        }

        try {
            $plan = $this->planService->plan($planKey);

            return $plan['name'] ?? ucfirst($planKey);
        } catch (\Throwable) {
            return ucfirst($planKey);
        }
    }

    private function timestampToDateTime(?string $timestamp): ?\Illuminate\Support\Carbon
    {
        if (! $timestamp) {
            return null;
        }

        if (is_numeric($timestamp)) {
            return now()->setTimestamp((int) $timestamp);
        }

        return \Illuminate\Support\Carbon::parse($timestamp);
    }

    public function updateSubscription(\App\Domain\Billing\Models\Subscription $subscription, string $newPriceId): void
    {
        $apiKey = config('services.lemonsqueezy.api_key');

        if (! $apiKey) {
            throw BillingException::missingConfiguration('Lemon Squeezy', 'api_key');
        }

        // LemonSqueezy uses variant_id for plan changes
        $response = $this->lemonSqueezyRequest($apiKey)
            ->patch("/subscriptions/{$subscription->provider_id}", [
                'data' => [
                    'type' => 'subscriptions',
                    'id' => $subscription->provider_id,
                    'attributes' => [
                        'variant_id' => (int) $newPriceId,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw BillingException::failedAction('Lemon Squeezy', 'update subscription', $response->body());
        }
    }

    public function cancelSubscription(\App\Domain\Billing\Models\Subscription $subscription): \Carbon\Carbon
    {
        $apiKey = config('services.lemonsqueezy.api_key');

        if (! $apiKey) {
            throw BillingException::missingConfiguration('Lemon Squeezy', 'api_key');
        }

        $response = $this->lemonSqueezyRequest($apiKey)
            ->delete("/subscriptions/{$subscription->provider_id}");

        if (! $response->successful()) {
            throw BillingException::failedAction('Lemon Squeezy', 'cancel subscription', $response->body());
        }

        // Get ends_at from response or use renews_at
        $endsAt = $response->json('data.attributes.ends_at');

        return $endsAt
            ? \Illuminate\Support\Carbon::parse($endsAt)
            : ($subscription->renews_at ?? now()->addMonth());
    }

    public function resumeSubscription(\App\Domain\Billing\Models\Subscription $subscription): void
    {
        $apiKey = config('services.lemonsqueezy.api_key');

        if (! $apiKey) {
            throw BillingException::missingConfiguration('Lemon Squeezy', 'api_key');
        }

        // LemonSqueezy uses PATCH to update subscription status
        $response = $this->lemonSqueezyRequest($apiKey)
            ->patch("/subscriptions/{$subscription->provider_id}", [
                'data' => [
                    'type' => 'subscriptions',
                    'id' => $subscription->provider_id,
                    'attributes' => [
                        'cancelled' => false,
                    ],
                ],
            ]);

        if (! $response->successful()) {
            throw BillingException::failedAction('Lemon Squeezy', 'resume subscription', $response->body());
        }
    }
}
