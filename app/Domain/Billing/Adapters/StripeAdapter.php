<?php

declare(strict_types=1);

namespace App\Domain\Billing\Adapters;

use App\Domain\Billing\Adapters\Stripe\Concerns\ResolvesStripeData;
use App\Domain\Billing\Adapters\Stripe\Handlers\StripeOrderHandler;
use App\Domain\Billing\Adapters\Stripe\Handlers\StripeSubscriptionHandler;
use App\Domain\Billing\Contracts\BillingRuntimeProvider;
use App\Domain\Billing\Contracts\StripeWebhookHandler;
use App\Domain\Billing\Data\CheckoutRequest;
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
use App\Enums\PaymentMode;
use App\Models\User;
use Illuminate\Http\Request;
use Stripe\StripeClient;
use Stripe\Webhook;

/**
 * Stripe billing provider adapter.
 *
 * This adapter handles all Stripe-related billing operations including:
 * - Webhook parsing and processing
 * - Checkout session creation
 *
 * Webhook event processing is delegated to specialized handlers for
 * maintainability and single responsibility.
 *
 * @see StripeCheckoutHandler
 * @see StripeSubscriptionHandler
 * @see StripeInvoiceHandler
 * @see StripePaymentHandler
 * @see StripeCustomerHandler
 */
class StripeAdapter implements BillingRuntimeProvider
{
    use ResolvesStripeData;

    private const SIGNATURE_HEADER = 'Stripe-Signature';

    /**
     * Registered webhook handlers.
     *
     * @var array<string, StripeWebhookHandler>
     */
    private array $handlers = [];

    public function __construct(
        private readonly array $config,
        private readonly BillingPlanService $planService,
    ) {
        $this->registerHandlers();
    }

    /**
     * {@inheritDoc}
     */
    public function provider(): string
    {
        return BillingProvider::Stripe->value;
    }

    /**
     * {@inheritDoc}
     *
     * @throws BillingException When webhook secret is missing or signature is invalid
     */
    public function parseWebhook(Request $request): WebhookPayload
    {
        $payload = $request->getContent();
        $signature = $request->header(self::SIGNATURE_HEADER);
        $secret = $this->config['webhook_secret'] ?? null;

        if (! $secret) {
            throw BillingException::missingConfiguration(BillingProvider::Stripe, 'webhook secret');
        }

        if (! $signature) {
            throw BillingException::webhookValidationFailed(BillingProvider::Stripe, 'signature header is missing');
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (\Exception $e) {
            throw BillingException::webhookValidationFailed(BillingProvider::Stripe, $e->getMessage());
        }

        return new WebhookPayload(
            id: $event->id,
            type: $event->type,
            payload: $event->toArray(),
            provider: $this->provider()
        );
    }

    /**
     * {@inheritDoc}
     */
    public function processEvent(WebhookEvent $event): void
    {
        $payload = $event->payload ?? [];
        $type = $payload['type'] ?? $event->type;
        $object = data_get($payload, 'data.object', []);

        if (! $type || ! $object) {
            return;
        }

        $handler = $this->getHandler($type);

        if ($handler) {
            $handler->handle($event, $object);
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws BillingException When Stripe secret is missing or sync fails
     */
    /**
     * {@inheritDoc}
     *
     * @throws BillingException When checkout creation fails
     */
    public function createCheckout(
        User $user,
        string $planKey,
        string $priceKey,
        int $quantity,
        string $successUrl,
        string $cancelUrl,
        ?Discount $discount = null
    ): TransactionDTO {
        $request = new CheckoutRequest(
            user: $user,
            planKey: $planKey,
            priceKey: $priceKey,
            quantity: $quantity,
            successUrl: $successUrl,
            cancelUrl: $cancelUrl,
            discount: $discount,
        );

        return $this->createCheckoutSession($request);
    }

    /**
     * Create a checkout session from a CheckoutRequest DTO.
     *
     * @throws BillingException When checkout creation fails
     */
    public function createCheckoutSession(CheckoutRequest $request): TransactionDTO
    {
        $plan = $this->planService->plan($request->planKey);
        $priceId = $this->planService->providerPriceId($this->provider(), $request->planKey, $request->priceKey);

        if (! $priceId) {
            throw BillingException::missingPriceId(BillingProvider::Stripe, $request->planKey, $request->priceKey);
        }

        $mode = $request->resolveMode($plan);
        $metadata = $request->metadata();

        $params = $this->buildCheckoutParams($request, $priceId, $mode, $metadata, $plan);

        try {
            $client = $this->stripeClient();
            $session = $client->checkout->sessions->create($params);

            if (! $session->url) {
                throw BillingException::checkoutFailed(BillingProvider::Stripe, 'session URL was not returned');
            }

            return new TransactionDTO(
                id: $session->id,
                url: $session->url,
                status: $session->payment_status // or status
            );
        } catch (BillingException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw BillingException::checkoutFailed(BillingProvider::Stripe, $e->getMessage());
        }
    }

    /**
     * {@inheritDoc}
     */
    public function createDiscount(Discount $discount): string
    {
        $payload = [
            'name' => $discount->name,
            'duration' => 'once', // Defaulting to once for simplicity in this generic implementation
        ];

        // Map Amount
        if ($discount->type === DiscountType::Percent->value) {
            $payload['percent_off'] = $discount->amount;
        } else {
            $payload['amount_off'] = $discount->amount;
            $payload['currency'] = strtolower((string) ($discount->currency ?? config('saas.billing.pricing.currency', 'USD')));
        }

        // Map Limits & Dates
        if ($discount->max_redemptions) {
            $payload['max_redemptions'] = $discount->max_redemptions;
        }

        if ($discount->ends_at) {
            $payload['redeem_by'] = $discount->ends_at->timestamp;
        }

        try {
            $client = $this->stripeClient();
            $coupon = $client->coupons->create($payload);

            return $coupon->id;
        } catch (\Exception $e) {
            // Note: If ID already exists, Stripe throws error.
            // In that case we might want to just return the ID or let it bubble.
            // Letting it bubble so user knows why it failed (duplicate).
            throw BillingException::failedAction(BillingProvider::Stripe, 'create discount', $e->getMessage());
        }
    }

    /**
     * Build checkout session parameters.
     *
     * @param  CheckoutRequest  $request  The checkout request
     * @param  string  $priceId  Stripe price ID
     * @param  string  $mode  Payment mode (subscription or payment)
     * @param  array<string, string>  $metadata  Session metadata
     * @param  \App\Domain\Billing\Data\Plan  $plan  The plan configuration
     * @return array<string, mixed>
     */
    private function buildCheckoutParams(
        CheckoutRequest $request,
        string $priceId,
        PaymentMode $mode,
        array $metadata,
        \App\Domain\Billing\Data\Plan $plan
    ): array {
        // Build descriptive payment type info
        $paymentTypeLabel = $mode === PaymentMode::Subscription ? 'Subscription' : 'One-time purchase';
        $planName = $plan->name ?? $request->planKey;

        $params = [
            'mode' => $mode->value,
            'line_items' => [
                [
                    'price' => $priceId,
                    'quantity' => max($request->quantity, 1),
                ],
            ],
            'success_url' => $request->successUrl,
            'cancel_url' => $request->cancelUrl,
            'client_reference_id' => (string) $request->user->id,
            'metadata' => $metadata,
            // Customize the submit button based on payment type
            'submit_type' => $mode === PaymentMode::Subscription ? 'auto' : 'pay',
            // Add custom text for better user experience
            'custom_text' => [
                'submit' => [
                    'message' => $mode === PaymentMode::Subscription
                        ? "You'll be charged automatically each billing period."
                        : 'This is a one-time payment. No recurring charges.',
                ],
            ],
        ];

        // Apply discount if provided
        if ($request->discount?->provider_id) {
            $type = $request->discount->provider_type ?: 'coupon';
            $params['discounts'] = match ($type) {
                'promotion_code' => [['promotion_code' => $request->discount->provider_id]],
                default => [['coupon' => $request->discount->provider_id]],
            };
        } else {
            $params['allow_promotion_codes'] = true;
        }

        // Set customer or customer email
        $customerId = BillingCustomer::query()
            ->where('user_id', $request->user->id)
            ->where('provider', $this->provider())
            ->value('provider_id');

        if ($customerId) {
            $params['customer'] = $customerId;
        } else {
            $params['customer_email'] = $request->user->email;
        }

        // Set mode-specific data
        if ($mode === PaymentMode::Subscription) {
            $params['subscription_data'] = ['metadata' => $metadata];
        } else {
            $params['payment_intent_data'] = ['metadata' => $metadata];
        }

        return $params;
    }

    /**
     * Register webhook handlers.
     *
     * NOTE: Product/Price handlers are intentionally NOT registered.
     * App-first mode = products/prices are managed in app, not synced from Stripe webhooks.
     */
    private function registerHandlers(): void
    {
        // Use the container to resolve handlers so dependencies (like other handlers) are injected automatically
        $handlers = [
            app(StripeSubscriptionHandler::class),
            app(StripeOrderHandler::class),
            // StripeProductHandler and StripePriceHandler REMOVED for app-first mode
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
    private function getHandler(string $eventType): ?StripeWebhookHandler
    {
        return $this->handlers[$eventType] ?? null;
    }

    public function updateSubscription(Subscription $subscription, string $newPriceId): void
    {
        $client = $this->stripeClient();

        // Get the current subscription to find the item ID
        $stripeSubscription = $client->subscriptions->retrieve($subscription->provider_id);
        $itemId = $stripeSubscription->items->data[0]->id ?? null;

        if (! $itemId) {
            throw BillingException::failedAction(BillingProvider::Stripe, 'update subscription', 'Could not find subscription item.');
        }

        // Update the subscription with the new price (proration by default)
        try {
            $client->subscriptions->update($subscription->provider_id, [
                'items' => [
                    [
                        'id' => $itemId,
                        'price' => $newPriceId,
                    ],
                ],
                'proration_behavior' => 'create_prorations',
            ]);
        } catch (\Exception $e) {
            throw BillingException::failedAction(BillingProvider::Stripe, 'update subscription', $e->getMessage());
        }
    }

    public function cancelSubscription(Subscription $subscription): \Carbon\Carbon
    {
        $client = $this->stripeClient();

        try {
            // Cancel at period end (graceful cancellation)
            $stripeSubscription = $client->subscriptions->update($subscription->provider_id, [
                'cancel_at_period_end' => true,
            ]);

            return \Carbon\Carbon::createFromTimestamp($stripeSubscription->current_period_end);
        } catch (\Exception $e) {
            throw BillingException::failedAction(BillingProvider::Stripe, 'cancel subscription', $e->getMessage());
        }
    }

    public function resumeSubscription(Subscription $subscription): void
    {
        $client = $this->stripeClient();

        try {
            $client->subscriptions->update($subscription->provider_id, [
                'cancel_at_period_end' => false,
            ]);
        } catch (\Exception $e) {
            throw BillingException::failedAction(BillingProvider::Stripe, 'resume subscription', $e->getMessage());
        }
    }

    private function stripeClient(): StripeClient
    {
        $secret = $this->config['secret_key'] ?? null;

        if (! $secret) {
            throw BillingException::missingConfiguration(BillingProvider::Stripe, 'secret');
        }

        // Note: Stripe SDK configures timeouts via Stripe\Stripe::setMaxNetworkRetries()
        // and request options, not via constructor. See Stripe SDK docs.
        $client = new StripeClient($secret);

        // Configure retries at the SDK level
        \Stripe\Stripe::setMaxNetworkRetries(
            (int) ($this->config['retries'] ?? 2)
        );

        return $client;
    }
}
