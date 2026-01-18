<?php

namespace App\Domain\Billing\Adapters;

use App\Domain\Billing\Models\BillingCustomer;
use App\Domain\Billing\Models\CheckoutIntent;
use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\WebhookEvent;
use App\Domain\Billing\Models\ProductProviderMapping;
use App\Domain\Billing\Models\PriceProviderMapping;
use App\Domain\Billing\Services\BillingPlanService;
use App\Domain\Billing\Services\DiscountService;
use App\Domain\Organization\Models\Team;
use App\Models\User;
use App\Notifications\CheckoutClaimNotification;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
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

        $checkoutIntentId = $this->resolveCheckoutIntentId($data);
        $teamId = $this->resolveTeamId($data);

        if (
            $checkoutIntentId
            && !$teamId
            && (str_contains($type, 'subscription') || str_contains($type, 'transaction') || str_contains($type, 'payment'))
        ) {
            $this->recordCheckoutIntentEvent($checkoutIntentId, $type, $data);
            return;
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

        $mapping = ProductProviderMapping::where('provider', 'paddle')
             ->where('provider_id', (string) $productId)
             ->first();

        if ($mapping && !$mapping->product && !config('saas.billing.allow_import_deleted', false)) {
            return;
        }

        $status = data_get($data, 'status', 'active');
        if (!$mapping && $status !== 'active') {
            return;
        }

        $productData = [
            'name' => $name ?? 'Paddle Product',
            'description' => data_get($data, 'description'),
            'is_active' => $status === 'active',
            'synced_at' => now(), // Still useful for knowing when we last saw it
        ];

        if ($mapping) {
            $product = $mapping->product;
            
            // Ensure key consistency if we want (optional, but good practice)
            // For now, just update metadata
            $product->update($productData);
        } else {
             $key = $customData['plan_key'] ?? $customData['product_key'] ?? Str::slug("paddle-{$productId}");
             // Ensure unique key globally
             // We can use a simpler check here or just rely on manual fix if conflict? 
             // Ideally we should use the same ensureUnique logic as Sync, but that is private in Command.
             // For now, let's assume if key exists, we try to find it.
             
             // Check if key exists
             $existingProduct = Product::where('key', $key)->first();
             if ($existingProduct) {
                 // Key taken. Is it THIS product (just missing mapping)?
                 // Be careful linking automatically. safer to suffix.
                 $key = $key . '-' . Str::random(4);
             }
             
             $productData['key'] = $key;
             $product = Product::create($productData);

             ProductProviderMapping::create([
                 'product_id' => $product->id,
                 'provider' => 'paddle',
                 'provider_id' => (string) $productId,
             ]);
        }
    }

    private function syncPrice(array $data, string $eventType): void
    {
        $priceId = data_get($data, 'id');
        $productId = data_get($data, 'product_id');

        if (!$priceId) {
            return;
        }

        $product = Product::query()
            ->whereHas('providerMappings', function ($q) use ($productId) {
                $q->where('provider', 'paddle')
                  ->where('provider_id', $productId);
            })
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

        $mapping = PriceProviderMapping::where('provider', $this->provider())
            ->where('provider_id', (string) $priceId)
            ->first();

        if ($mapping && !$mapping->price && !config('saas.billing.allow_import_deleted', false)) {
            return;
        }

        $status = data_get($data, 'status', 'active');
        if (!$mapping && $status !== 'active') {
            return;
        }

        $priceData = [
            'product_id' => $product->id,
            'key' => $key,
            'label' => data_get($data, 'description', ucfirst($key)),
            'interval' => $interval,
            'interval_count' => $intervalCount,
            'currency' => strtoupper($unitPrice['currency_code'] ?? 'USD'),
            'amount' => (int) ($unitPrice['amount'] ?? 0),
            'type' => 'flat',
            'is_active' => $status === 'active',
        ];

        if ($mapping) {
            $mapping->price->update($priceData);
            return;
        }

        $price = Price::create($priceData);

        PriceProviderMapping::create([
            'price_id' => $price->id,
            'provider' => $this->provider(),
            'provider_id' => (string) $priceId,
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
        $amount = (int) (data_get($data, 'amount') 
            ?? data_get($data, 'amount_total') 
            ?? data_get($data, 'details.totals.grand_total') 
            ?? data_get($data, 'details.totals.total') 
            ?? 0);
        $currency = strtoupper((string) (data_get($data, 'currency_code') 
            ?? data_get($data, 'currency') 
            ?? data_get($data, 'details.totals.currency_code') 
            ?? 'USD'));

        $order = Order::query()->updateOrCreate(
            [
                'provider' => $this->provider(),
                'provider_id' => (string) $orderId,
            ],
            [
                'team_id' => $teamId,
                'provider_order_id' => (string) $orderId,
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

        if (!$teamId) {
            return null;
        }

        $teamId = (int) $teamId;

        if (!Team::query()->whereKey($teamId)->exists()) {
            Log::warning('Paddle webhook references missing team', [
                'team_id' => $teamId,
                'event_id' => data_get($data, 'id') ?? data_get($data, 'subscription_id') ?? data_get($data, 'transaction_id'),
            ]);

            return null;
        }

        return $teamId;
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
        $provider = $this->provider();

        if ($providerId) {
            BillingCustomer::query()->updateOrCreate(
                [
                    'provider' => $provider,
                    'provider_id' => $providerId,
                ],
                [
                    'team_id' => $teamId,
                    'email' => $email,
                ]
            );
            return;
        }

        BillingCustomer::query()->updateOrCreate(
            [
                'team_id' => $teamId,
                'provider' => $provider,
            ],
            [
                'email' => $email,
            ]
        );
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
        
        // Extract invoice identifiers
        $transactionId = data_get($data, 'id');
        $invoiceNumber = data_get($data, 'invoice_number');
        $invoiceId = data_get($data, 'invoice_id');
        
        // Extract customer information (collected by Paddle during checkout)
        $customer = data_get($data, 'customer', []);
        $address = data_get($data, 'address', []) ?? data_get($customer, 'address', []);
        $billingDetails = data_get($data, 'billing_details', []);
        
        // Extract tax breakdown
        $details = data_get($data, 'details', []);
        $totals = data_get($details, 'totals', []);
        $subtotal = (int) data_get($totals, 'subtotal', 0);
        $taxAmount = (int) data_get($totals, 'tax', 0);
        
        // PDF URLs (may not be available immediately)
        $invoiceUrl = data_get($billingDetails, 'invoice_url') 
            ?? data_get($data, 'invoice_url') 
            ?? data_get($data, 'checkout.invoice_url');
        
        $invoicePdf = data_get($billingDetails, 'invoice_pdf')
            ?? data_get($data, 'invoice_pdf')
            ?? data_get($data, 'checkout.invoice_pdf');

        $invoice = Invoice::query()->updateOrCreate(
            [
                'provider' => $this->provider(),
                'provider_id' => (string) $transactionId,
            ],
            [
                'team_id' => $teamId,
                'order_id' => $order->id,
                'invoice_number' => $invoiceNumber,
                'provider_invoice_id' => $invoiceId,
                'status' => $normalizedStatus,
                
                // Customer information (from Paddle checkout)
                'customer_name' => data_get($customer, 'name'),
                'customer_email' => data_get($customer, 'email'),
                'billing_address' => [
                    'line1' => data_get($address, 'first_line'),
                    'line2' => data_get($address, 'second_line'),
                    'city' => data_get($address, 'city'),
                    'postal_code' => data_get($address, 'postal_code'),
                    'country' => data_get($address, 'country_code'),
                ],
                'customer_vat_number' => data_get($billingDetails, 'tax_identifier'),
                
                // Financial breakdown
                'amount_due' => $amount,
                'amount_paid' => $paid ? $amount : 0,
                'subtotal' => $subtotal,
                'tax_amount' => $taxAmount,
                'tax_rate' => $this->calculateTaxRate($subtotal, $taxAmount),
                'currency' => $currency,
                
                // Timestamps
                'issued_at' => $this->timestampToDateTime(data_get($data, 'created_at') ?? data_get($data, 'billed_at')),
                'paid_at' => $paid ? now() : null,
                
                // PDF URLs (not cached yet, will be fetched on-demand)
                'hosted_invoice_url' => $invoiceUrl,
                'pdf_url' => $invoicePdf,
                'pdf_url_expires_at' => null, // Will be set when fetched via API
                
                'metadata' => Arr::only($data, [
                    'id', 'status', 'items', 'custom_data', 
                    'invoice_number', 'invoice_id', 'billing_details',
                    'customer', 'address', 'details'
                ]),
            ]
        );
        
        // Sync line items
        $this->syncInvoiceLineItems($invoice, data_get($data, 'items', []));
    }
    
    /**
     * Calculate tax rate as percentage from subtotal and tax amount.
     */
    private function calculateTaxRate(int $subtotal, int $taxAmount): ?float
    {
        if ($subtotal > 0 && $taxAmount > 0) {
            return round(($taxAmount / $subtotal) * 100, 2);
        }
        
        return null;
    }
    
    /**
     * Sync invoice line items from Paddle transaction data.
     */
    private function syncInvoiceLineItems(Invoice $invoice, array $items): void
    {
        // Clear existing line items
        $invoice->lineItems()->delete();
        
        foreach ($items as $item) {
            $priceData = data_get($item, 'price', []);
            $productData = data_get($item, 'product', []);
            $itemTotals = data_get($item, 'totals', []);
            $billingPeriod = data_get($item, 'billing_period', []);
            
            $invoice->lineItems()->create([
                'product_name' => data_get($productData, 'name') 
                    ?? data_get($priceData, 'description')
                    ?? 'Product',
                'description' => data_get($priceData, 'name') 
                    ?? data_get($priceData, 'description'),
                'quantity' => (int) data_get($item, 'quantity', 1),
                'unit_price' => (int) data_get($priceData, 'unit_price.amount', 0),
                'total_amount' => (int) data_get($itemTotals, 'total', 0),
                'tax_rate' => null, // Paddle doesn't provide per-item tax rate
                'period_start' => data_get($billingPeriod, 'starts_at') 
                    ? Carbon::parse(data_get($billingPeriod, 'starts_at'))->toDateString()
                    : null,
                'period_end' => data_get($billingPeriod, 'ends_at')
                    ? Carbon::parse(data_get($billingPeriod, 'ends_at'))->toDateString()
                    : null,
                'metadata' => $item,
            ]);
        }
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

    private function resolveCheckoutIntentId(array $data): ?string
    {
        $intentId = data_get($data, 'custom_data.checkout_intent_id')
            ?? data_get($data, 'metadata.checkout_intent_id');

        return $intentId ? (string) $intentId : null;
    }

    private function recordCheckoutIntentEvent(string $intentId, string $type, array $data): void
    {
        $intent = CheckoutIntent::query()->find($intentId);

        if (!$intent) {
            return;
        }

        $payload = $intent->payload ?? [];
        $entry = [
            'type' => $type,
            'data' => $data,
        ];

        $lowerType = strtolower($type);

        if (str_contains($lowerType, 'subscription')) {
            $payload['subscription'] = $entry;
            $subscriptionId = data_get($data, 'id') ?? data_get($data, 'subscription_id');
            if ($subscriptionId && !$intent->provider_subscription_id) {
                $intent->provider_subscription_id = (string) $subscriptionId;
            }
        }

        if (str_contains($lowerType, 'transaction') || str_contains($lowerType, 'payment')) {
            $payload['transaction'] = $entry;
            $transactionId = data_get($data, 'id') ?? data_get($data, 'transaction_id');
            if ($transactionId && !$intent->provider_transaction_id) {
                $intent->provider_transaction_id = (string) $transactionId;
            }
        }

        $email = data_get($data, 'customer_email')
            ?? data_get($data, 'customer.email');

        if ($email) {
            $intent->email = $email;
        }

        $customerId = data_get($data, 'customer_id')
            ?? data_get($data, 'customer.id');

        if ($customerId) {
            $intent->provider_customer_id = (string) $customerId;

            if (!$intent->email) {
                $existingEmail = BillingCustomer::query()
                    ->where('provider', $this->provider())
                    ->where('provider_id', (string) $customerId)
                    ->value('email');

                if ($existingEmail) {
                    $intent->email = $existingEmail;
                } else {
                    $resolvedEmail = $this->fetchCustomerEmail((string) $customerId);
                    if ($resolvedEmail) {
                        $intent->email = $resolvedEmail;
                    }
                }
            }
        }

        $status = strtolower((string) (data_get($data, 'status') ?? data_get($data, 'state') ?? ''));

        if ($this->isPaidStatus($status) && $intent->status !== 'claimed') {
            $intent->status = 'paid';
        }

        $intent->payload = $payload;
        $intent->save();

        if ($intent->status === 'paid' && $intent->email && !$intent->claim_sent_at) {
            Notification::route('mail', $intent->email)
                ->notify(new CheckoutClaimNotification($intent));

            $intent->claim_sent_at = now();
            $intent->save();
        }
    }

    public function finalizeCheckoutIntent(CheckoutIntent $intent, Team $team, User $user): void
    {
        $payload = $intent->payload ?? [];

        if (!empty($payload['subscription']['data'])) {
            $data = $this->injectIntentCustomData($payload['subscription']['data'], $team, $user, $intent);
            $type = (string) ($payload['subscription']['type'] ?? 'subscription');
            $this->syncSubscription($data, $type);
        }

        if (!empty($payload['transaction']['data'])) {
            $data = $this->injectIntentCustomData($payload['transaction']['data'], $team, $user, $intent);
            $type = (string) ($payload['transaction']['type'] ?? 'transaction');
            $this->syncOrder($data, $type);
        }

        if ($intent->provider_customer_id || $intent->email) {
            $this->syncBillingCustomer($team->id, $intent->provider_customer_id, $intent->email);
        }
    }

    private function injectIntentCustomData(array $data, Team $team, User $user, CheckoutIntent $intent): array
    {
        $customData = $data['custom_data'] ?? [];

        $customData['team_id'] = $team->id;
        $customData['user_id'] = $user->id;
        $customData['plan_key'] = $intent->plan_key;
        $customData['price_key'] = $intent->price_key;

        if ($intent->discount_id) {
            $customData['discount_id'] = $intent->discount_id;
        }

        if ($intent->discount_code) {
            $customData['discount_code'] = $intent->discount_code;
        }

        $data['custom_data'] = $customData;

        return $data;
    }

    private function isPaidStatus(string $status): bool
    {
        return in_array($status, ['paid', 'completed', 'complete', 'active', 'trialing'], true);
    }

    private function fetchCustomerEmail(string $customerId): ?string
    {
        $apiKey = config('services.paddle.api_key');

        if (!$apiKey) {
            return null;
        }

        $response = $this->paddleRequest($apiKey)
            ->get("/customers/{$customerId}");

        if (!$response->successful()) {
            return null;
        }

        $email = $response->json('data.email');

        return $email ? (string) $email : null;
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

    public function archiveProduct(string $providerId): void
    {
        $apiKey = config('services.paddle.api_key');

        if (!$apiKey) {
            throw new RuntimeException('Paddle API key is missing.');
        }

        $url = $this->paddleBaseUrl() . "/products/{$providerId}";
        \Illuminate\Support\Facades\Log::info("PaddleAdapter: Attempting Synchronous Archive on: {$url}");

        $response = $this->paddleRequest($apiKey)
            ->patch("/products/{$providerId}", [
                'status' => 'archived',
            ]);

        if (!$response->successful()) {
            $error = $response->json('error');
            if (data_get($error, 'code') === 'entity_archived') {
                \Illuminate\Support\Facades\Log::info("Paddle value already archived: Product {$providerId}");
                return;
            }

            throw new RuntimeException('Paddle archive product failed: ' . $response->body());
        }
    }

    public function archivePrice(string $providerId): void
    {
        $apiKey = config('services.paddle.api_key');

        if (!$apiKey) {
            throw new RuntimeException('Paddle API key is missing.');
        }

        $url = $this->paddleBaseUrl() . "/prices/{$providerId}";
        \Illuminate\Support\Facades\Log::info("PaddleAdapter: Attempting Synchronous Archive on: {$url}");

        $response = $this->paddleRequest($apiKey)
            ->patch("/prices/{$providerId}", [
                'status' => 'archived',
            ]);

        if (!$response->successful()) {
            $error = $response->json('error');
            if (data_get($error, 'code') === 'entity_archived') {
                \Illuminate\Support\Facades\Log::info("Paddle value already archived: Price {$providerId}");
                return;
            }

            throw new RuntimeException('Paddle archive price failed: ' . $response->body());
        }
    }
}
