<?php

namespace App\Domain\Billing\Adapters\Stripe\Handlers;

use App\Domain\Billing\Adapters\Stripe\Concerns\ResolvesStripeData;
use App\Domain\Billing\Contracts\StripeWebhookHandler;
use App\Domain\Billing\Models\BillingCustomer;
use App\Domain\Billing\Models\WebhookEvent;

/**
 * Handles Stripe customer-related webhook events.
 *
 * Processes: customer.created, customer.updated, customer.deleted
 */
class StripeCustomerHandler implements StripeWebhookHandler
{
    use ResolvesStripeData;

    /**
     * {@inheritDoc}
     */
    public function eventTypes(): array
    {
        return [
            'customer.created',
            'customer.updated',
            'customer.deleted',
        ];
    }

    /**
     * {@inheritDoc}
     */
    public function handle(WebhookEvent $event, array $object): void
    {
        $this->syncCustomer($object);
    }

    /**
     * Sync customer data from Stripe webhook.
     */
    private function syncCustomer(array $object): void
    {
        $providerId = data_get($object, 'id');
        $email = data_get($object, 'email');

        if (!$providerId) {
            return;
        }

        $teamId = $this->resolveTeamIdFromMetadata($object);

        $customer = BillingCustomer::query()
            ->where('provider', $this->provider())
            ->where('provider_id', $providerId)
            ->first();

        if (!$customer && $teamId) {
            BillingCustomer::create([
                'team_id' => $teamId,
                'provider' => $this->provider(),
                'provider_id' => $providerId,
                'email' => $email,
            ]);

            return;
        }

        if ($customer) {
            $customer->update([
                'email' => $email ?: $customer->email,
                'provider_id' => $providerId,
            ]);
        }
    }
}
