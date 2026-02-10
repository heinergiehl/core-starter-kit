<?php

namespace App\Domain\Billing\Adapters\Stripe\Handlers;

use App\Domain\Billing\Adapters\Stripe\Concerns\ResolvesStripeData;
use App\Domain\Billing\Contracts\StripeWebhookHandler;
use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\WebhookEvent;
use App\Domain\Billing\Services\BillingPlanService;
use App\Domain\Billing\Services\CheckoutService;
use App\Domain\Billing\Services\DiscountService;
use App\Domain\Billing\Jobs\SyncSubscriptionFromStripeJob;
use App\Models\User;
use Illuminate\Support\Arr;

/**
 * Handles Stripe order-related webhook events.
 *
 * Processes: checkout.session.completed, charge.refunded
 */
class StripeOrderHandler implements StripeWebhookHandler
{
    use ResolvesStripeData;

    public function __construct(
        protected StripeSubscriptionHandler $subscriptionHandler,
        private readonly CheckoutService $checkoutService,
        private readonly DiscountService $discountService,
        private readonly BillingPlanService $billingPlanService,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function eventTypes(): array
    {
        return [
            'checkout.session.completed',
            'charge.refunded',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function handle(WebhookEvent $event, array $object): void
    {
        $payload = $event->payload ?? [];
        $eventType = $payload['type'] ?? $event->type;

        if ($eventType === 'charge.refunded') {
            $this->handleChargeRefunded($object);
            return;
        }

        $this->handleCheckoutSessionCompleted($object);
    }

    /**
     * Handle completed checkout session.
     */
    private function handleCheckoutSessionCompleted(array $object): void
    {
        $userId = $this->resolveUserIdFromMetadata($object);
        $customerId = data_get($object, 'customer');
        $email = data_get($object, 'customer_details.email') ?? data_get($object, 'customer_email');

        if ($userId) {
            $this->syncBillingCustomer($userId, $customerId, $email);
        }

        $mode = data_get($object, 'mode');

        if ($mode === 'subscription') {
            $this->handleSubscriptionCheckout($object, $userId);

            return;
        }

        if ($mode === 'payment') {
            $this->handlePaymentCheckout($object, $userId);
        }
    }

    /**
     * Handle subscription checkout completion.
     */
    private function handleSubscriptionCheckout(array $object, ?int $userId): void
    {
        $subscriptionId = data_get($object, 'subscription');

        if (! $subscriptionId || ! $userId) {
            return;
        }

        $planKey = $this->resolvePlanKey($object) ?? 'unknown';
        $sessionId = data_get($object, 'id');
        $metadata = Arr::only($object, [
            'id',
            'subscription',
            'customer',
            'mode',
            'status',
            'payment_status',
            'metadata',
        ]);
        $metadata['session_id'] = $sessionId;

        $paymentStatus = data_get($object, 'payment_status');
        $status = match ($paymentStatus) {
            'paid' => \App\Enums\SubscriptionStatus::Active,
            'no_payment_required' => \App\Enums\SubscriptionStatus::Trialing,
            default => \App\Enums\SubscriptionStatus::Incomplete,
        };

        Subscription::query()->updateOrCreate(
            [
                'provider' => $this->provider(),
                'provider_id' => $subscriptionId,
            ],
            [
                'user_id' => $userId,
                'plan_key' => $planKey,
                'status' => $status,
                'quantity' => (int) (data_get($object, 'quantity') ?? 1),
                'metadata' => $metadata,
            ]
        );

        $this->recordDiscountRedemption(
            $object,
            $userId,
            $planKey,
            data_get($object, 'metadata.price_key'),
            (string) $subscriptionId
        );

        SyncSubscriptionFromStripeJob::dispatch((string) $subscriptionId);
    }

    /**
     * Handle payment (one-time) checkout completion.
     */
    private function handlePaymentCheckout(array $object, ?int $userId): void
    {
        $providerId = data_get($object, 'payment_intent') ?: data_get($object, 'id');

        if (! $providerId || ! $userId) {
            return;
        }

        $planKey = $this->resolvePlanKey($object);
        $amount = (int) (data_get($object, 'amount_total') ?? 0);
        $currency = strtoupper((string) data_get($object, 'currency', 'USD'));
        $paymentStatus = data_get($object, 'payment_status');

        $order = Order::query()->updateOrCreate(
            [
                'provider' => $this->provider(),
                'provider_id' => $providerId,
            ],
            [
                'user_id' => $userId,
                'plan_key' => $planKey,
                'status' => $paymentStatus === 'paid' ? \App\Enums\OrderStatus::Paid : \App\Enums\OrderStatus::Pending,
                'amount' => $amount,
                'currency' => $currency,
                'paid_at' => $paymentStatus === 'paid' ? now() : null,
                'metadata' => [
                    'session_id' => data_get($object, 'id'),
                    'payment_intent' => data_get($object, 'payment_intent'),
                    'metadata' => data_get($object, 'metadata', []),
                ],
            ]
        );

        $this->recordDiscountRedemption(
            $object,
            $userId,
            $planKey,
            data_get($object, 'metadata.price_key'),
            (string) $providerId
        );

        // Send payment success notification for one-time purchases (consistent with Paddle)
        if ($paymentStatus === 'paid' && ! $order->payment_success_email_sent_at) {
            $user = \App\Models\User::find($userId);
            if ($user) {
                $user->notify(new \App\Notifications\PaymentSuccessfulNotification(
                    planName: $this->resolvePlanName($planKey),
                    amount: $amount,
                    currency: $currency,
                    receiptUrl: null, // Invoice URL arrives via invoice.paid webhook
                ));
                $order->forceFill(['payment_success_email_sent_at' => now()])->save();
            }
        }

        // Verify user email after successful payment
        if ($paymentStatus === 'paid') {
            $this->checkoutService->verifyUserAfterPayment($userId);
        }
    }

    /**
     * Record discount redemption if applicable.
     */
    private function recordDiscountRedemption(
        array $object,
        int $userId,
        ?string $planKey,
        ?string $priceKey,
        string $providerId
    ): void {
        $metadata = data_get($object, 'metadata', []);
        $discountId = $metadata['discount_id'] ?? null;
        $discountCode = $metadata['discount_code'] ?? null;

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

        $this->discountService->recordRedemption(
            $discount,
            $user,
            $this->provider(),
            $providerId,
            $planKey,
            $priceKey,
            [
                'source' => 'checkout.session.completed',
                'session_id' => data_get($object, 'id'),
            ]
        );
    }

    /**
     * Resolve human-readable plan name from plan key.
     */
    private function resolvePlanName(?string $planKey): string
    {
        if (! $planKey) {
            return 'product';
        }

        try {
            $plan = $this->billingPlanService->plan($planKey);

            return $plan->name ?: ucfirst($planKey);
        } catch (\Throwable) {
            return ucfirst($planKey);
        }
    }
    /**
     * Handle refunded charge.
     */
    private function handleChargeRefunded(array $object): void
    {
        $paymentIntent = data_get($object, 'payment_intent');
        $providerId = $paymentIntent ?: data_get($object, 'id');

        if (! $providerId) {
            return;
        }

        Order::query()
            ->where('provider', $this->provider())
            ->where('provider_id', $providerId)
            ->update([
                'status' => 'refunded',
                'refunded_at' => now(),
            ]);
    }
}
