<?php

namespace App\Domain\Billing\Adapters\Stripe\Handlers;

use App\Domain\Billing\Adapters\Stripe\Concerns\ResolvesStripeData;
use App\Domain\Billing\Contracts\StripeWebhookHandler;
use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\WebhookEvent;
use App\Domain\Billing\Services\DiscountService;
use App\Domain\Organization\Models\Team;
use App\Models\User;
use Illuminate\Support\Arr;
use Stripe\StripeClient;

/**
 * Handles Stripe checkout session completed webhook events.
 *
 * Processes: checkout.session.completed
 */
class StripeCheckoutHandler implements StripeWebhookHandler
{
    use ResolvesStripeData;

    public function __construct(
        protected StripeSubscriptionHandler $subscriptionHandler,
    ) {}

    /**
     * {@inheritDoc}
     */
    public function eventTypes(): array
    {
        return [
            'checkout.session.completed',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function handle(WebhookEvent $event, array $object): void
    {
        $this->handleCheckoutSessionCompleted($object);
    }

    /**
     * Handle completed checkout session.
     */
    private function handleCheckoutSessionCompleted(array $object): void
    {
        $teamId = $this->resolveTeamIdFromMetadata($object);
        $customerId = data_get($object, 'customer');
        $email = data_get($object, 'customer_details.email') ?? data_get($object, 'customer_email');

        if ($teamId) {
            $this->syncBillingCustomer($teamId, $customerId, $email);
        }

        $mode = data_get($object, 'mode');

        if ($mode === 'subscription') {
            $this->handleSubscriptionCheckout($object, $teamId);
            return;
        }

        if ($mode === 'payment') {
            $this->handlePaymentCheckout($object, $teamId);
        }
    }

    /**
     * Handle subscription checkout completion.
     */
    private function handleSubscriptionCheckout(array $object, ?int $teamId): void
    {
        $subscriptionId = data_get($object, 'subscription');

        if (!$subscriptionId || !$teamId) {
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
            'paid' => 'active',
            'no_payment_required' => 'trialing',
            default => 'processing',
        };

        Subscription::query()->updateOrCreate(
            [
                'provider' => $this->provider(),
                'provider_id' => $subscriptionId,
            ],
            [
                'team_id' => $teamId,
                'plan_key' => $planKey,
                'status' => $status,
                'quantity' => (int) (data_get($object, 'quantity') ?? 1),
                'metadata' => $metadata,
            ]
        );

        $this->recordDiscountRedemption(
            $object,
            $teamId,
            $planKey,
            data_get($object, 'metadata.price_key'),
            (string) $subscriptionId
        );

        $this->subscriptionHandler->syncSubscriptionFromStripe($subscriptionId);
    }

    /**
     * Handle payment (one-time) checkout completion.
     */
    private function handlePaymentCheckout(array $object, ?int $teamId): void
    {
        $providerId = data_get($object, 'payment_intent') ?: data_get($object, 'id');

        if (!$providerId || !$teamId) {
            return;
        }

        $planKey = $this->resolvePlanKey($object);
        $amount = (int) (data_get($object, 'amount_total') ?? 0);
        $currency = strtoupper((string) data_get($object, 'currency', 'USD'));
        $paymentStatus = data_get($object, 'payment_status');

        Order::query()->updateOrCreate(
            [
                'provider' => $this->provider(),
                'provider_id' => $providerId,
            ],
            [
                'team_id' => $teamId,
                'plan_key' => $planKey,
                'status' => $paymentStatus === 'paid' ? 'paid' : 'pending',
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
            $teamId,
            $planKey,
            data_get($object, 'metadata.price_key'),
            (string) $providerId
        );
    }

    /**
     * Record discount redemption if applicable.
     */
    private function recordDiscountRedemption(
        array $object,
        int $teamId,
        ?string $planKey,
        ?string $priceKey,
        string $providerId
    ): void {
        $metadata = data_get($object, 'metadata', []);
        $discountId = $metadata['discount_id'] ?? null;
        $discountCode = $metadata['discount_code'] ?? null;

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

        $userId = $metadata['user_id'] ?? null;
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
                'source' => 'checkout.session.completed',
                'session_id' => data_get($object, 'id'),
            ]
        );
    }
}
