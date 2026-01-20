<?php

namespace App\Domain\Billing\Adapters;

use App\Domain\Billing\Adapters\Stripe\Concerns\ResolvesStripeData;
use App\Domain\Billing\Adapters\Stripe\Handlers\StripeCheckoutHandler;
use App\Domain\Billing\Adapters\Stripe\Handlers\StripeCustomerHandler;
use App\Domain\Billing\Adapters\Stripe\Handlers\StripeInvoiceHandler;
use App\Domain\Billing\Adapters\Stripe\Handlers\StripePaymentHandler;
use App\Domain\Billing\Adapters\Stripe\Handlers\StripePriceHandler;
use App\Domain\Billing\Adapters\Stripe\Handlers\StripeProductHandler;
use App\Domain\Billing\Adapters\Stripe\Handlers\StripeSubscriptionHandler;
use App\Domain\Billing\Contracts\StripeWebhookHandler;
use App\Domain\Billing\Data\CheckoutRequest;
use App\Domain\Billing\Exceptions\BillingException;
use App\Domain\Billing\Models\BillingCustomer;
use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\WebhookEvent;
use App\Domain\Billing\Services\BillingPlanService;
use App\Domain\Organization\Models\Team;
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
 * - Seat quantity synchronization
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
class StripeAdapter implements BillingProviderAdapter
{
    use ResolvesStripeData;

    /**
     * Registered webhook handlers.
     *
     * @var array<string, StripeWebhookHandler>
     */
    private array $handlers = [];

    public function __construct()
    {
        $this->registerHandlers();
    }

    /**
     * {@inheritDoc}
     */
    public function provider(): string
    {
        return 'stripe';
    }

    /**
     * {@inheritDoc}
     *
     * @throws BillingException When webhook secret is missing or signature is invalid
     */
    public function parseWebhook(Request $request): array
    {
        $payload = $request->getContent();
        $signature = $request->header('Stripe-Signature');
        $secret = config('services.stripe.webhook_secret');

        if (!$secret) {
            throw BillingException::missingConfiguration('Stripe', 'webhook secret');
        }

        if (!$signature) {
            throw BillingException::webhookValidationFailed('Stripe', 'signature header is missing');
        }

        try {
            $event = Webhook::constructEvent($payload, $signature, $secret);
        } catch (\Exception $e) {
            throw BillingException::webhookValidationFailed('Stripe', $e->getMessage());
        }

        return [
            'id' => $event->id,
            'type' => $event->type,
            'payload' => $event->toArray(),
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function processEvent(WebhookEvent $event): void
    {
        $payload = $event->payload ?? [];
        $type = $payload['type'] ?? $event->type;
        $object = data_get($payload, 'data.object', []);

        if (!$type || !$object) {
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

        $client = $this->stripeClient();
        $itemId = $this->resolveSubscriptionItemId($subscription, $client);

        if (!$itemId) {
            throw BillingException::seatSyncFailed('Stripe', 'subscription item ID not found');
        }

        try {
            $client->subscriptions->update($subscription->provider_id, [
                'items' => [
                    [
                        'id' => $itemId,
                        'quantity' => $quantity,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            throw BillingException::seatSyncFailed('Stripe', $e->getMessage());
        }
    }

    /**
     * {@inheritDoc}
     *
     * @throws BillingException When checkout creation fails
     */
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
        $request = new CheckoutRequest(
            team: $team,
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
    public function createCheckoutSession(CheckoutRequest $request): string
    {
        $planService = app(BillingPlanService::class);
        $plan = $planService->plan($request->planKey);
        $priceId = $planService->providerPriceId($this->provider(), $request->planKey, $request->priceKey);

        if (!$priceId) {
            throw BillingException::missingPriceId('Stripe', $request->planKey, $request->priceKey);
        }

        $mode = $request->resolveMode($plan);
        $metadata = $request->metadata();

        $params = $this->buildCheckoutParams($request, $priceId, $mode, $metadata, $plan);

        try {
            $client = $this->stripeClient();
            $session = $client->checkout->sessions->create($params);

            if (!$session->url) {
                throw BillingException::checkoutFailed('Stripe', 'session URL was not returned');
            }

            return $session->url;
        } catch (BillingException $e) {
            throw $e;
        } catch (\Exception $e) {
            throw BillingException::checkoutFailed('Stripe', $e->getMessage());
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
        if ($discount->type === 'percent') {
            $payload['percent_off'] = $discount->amount;
        } else {
            $payload['amount_off'] = $discount->amount;
            $payload['currency'] = $discount->currency ?? 'USD';
        }
        
        // Map Limits & Dates
        if ($discount->max_redemptions) {
             $payload['max_redemptions'] = $discount->max_redemptions;
        }
        
        if ($discount->ends_at) {
             $payload['redeem_by'] = $discount->ends_at->timestamp;
        }
        
        // Try to use the code as ID for better readability in Stripe Dashboard
        $payload['id'] = $discount->code;

        try {
            $client = $this->stripeClient();
            $coupon = $client->coupons->create($payload);
            return $coupon->id;
        } catch (\Exception $e) {
            // Note: If ID already exists, Stripe throws error. 
            // In that case we might want to just return the ID or let it bubble.
            // Letting it bubble so user knows why it failed (duplicate).
            throw BillingException::failedAction('Stripe', 'create discount', $e->getMessage());
        }
    }

    /**
     * Build checkout session parameters.
     *
     * @param CheckoutRequest $request The checkout request
     * @param string $priceId Stripe price ID
     * @param string $mode Payment mode (subscription or payment)
     * @param array<string, string> $metadata Session metadata
     * @param array<string, mixed> $plan The plan configuration
     * @return array<string, mixed>
     */
    private function buildCheckoutParams(
        CheckoutRequest $request,
        string $priceId,
        string $mode,
        array $metadata,
        array $plan = []
    ): array {
        // Build descriptive payment type info
        $paymentTypeLabel = $mode === 'subscription' ? 'Subscription' : 'One-time purchase';
        $planName = $plan['name'] ?? $request->planKey;
        
        $params = [
            'mode' => $mode,
            'line_items' => [
                [
                    'price' => $priceId,
                    'quantity' => max($request->quantity, 1),
                ],
            ],
            'success_url' => $request->successUrl,
            'cancel_url' => $request->cancelUrl,
            'client_reference_id' => (string) $request->team->id,
            'metadata' => $metadata,
            'allow_promotion_codes' => true,
            // Customize the submit button based on payment type
            'submit_type' => $mode === 'subscription' ? 'auto' : 'pay',
            // Add custom text for better user experience
            'custom_text' => [
                'submit' => [
                    'message' => $mode === 'subscription' 
                        ? "You'll be charged automatically each billing period." 
                        : "This is a one-time payment. No recurring charges.",
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
        }

        // Set customer or customer email
        $customerId = BillingCustomer::query()
            ->where('team_id', $request->team->id)
            ->where('provider', $this->provider())
            ->value('provider_id');

        if ($customerId) {
            $params['customer'] = $customerId;
        } else {
            $params['customer_email'] = $request->user->email;
        }

        // Set mode-specific data
        if ($mode === 'subscription') {
            $params['subscription_data'] = ['metadata' => $metadata];
        } else {
            $params['payment_intent_data'] = ['metadata' => $metadata];
        }

        return $params;
    }

    /**
     * Register webhook handlers.
     */
    private function registerHandlers(): void
    {
        $subscriptionHandler = new StripeSubscriptionHandler();

        $handlers = [
            new StripeCustomerHandler(),
            $subscriptionHandler,
            new StripeCheckoutHandler($subscriptionHandler),
            new StripeInvoiceHandler(),
            new StripePaymentHandler(),
            new StripeProductHandler(),
            new StripePriceHandler(),
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

    /**
     * Resolve subscription item ID for seat sync.
     */
    private function resolveSubscriptionItemId(Subscription $subscription, StripeClient $client): ?string
    {
        $metadata = $subscription->metadata ?? [];
        $itemId = data_get($metadata, 'stripe_item_id');

        if ($itemId) {
            return $itemId;
        }

        $stripeSubscription = $client->subscriptions->retrieve($subscription->provider_id, []);
        $item = $stripeSubscription->items->data[0] ?? null;

        return $item?->id;
    }

    private function stripeClient(): StripeClient
    {
        $secret = config('services.stripe.secret');

        if (!$secret) {
            throw BillingException::missingConfiguration('Stripe', 'secret');
        }

        // Note: Stripe SDK configures timeouts via Stripe\Stripe::setMaxNetworkRetries()
        // and request options, not via constructor. See Stripe SDK docs.
        $client = new StripeClient($secret);
        
        // Configure retries at the SDK level
        \Stripe\Stripe::setMaxNetworkRetries(
            (int) config('saas.billing.provider_api.retries.stripe', 2)
        );

        return $client;
    }
}
