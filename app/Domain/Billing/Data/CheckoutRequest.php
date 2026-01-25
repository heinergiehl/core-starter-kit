<?php

namespace App\Domain\Billing\Data;

use App\Domain\Billing\Models\Discount;
use App\Models\User;

/**
 * Data Transfer Object for checkout session requests.
 *
 * Encapsulates all data needed to create a checkout session across
 * any billing provider.
 */
readonly class CheckoutRequest
{
    /**
     * @param  User  $user  The user initiating the checkout
     * @param  string  $planKey  The plan identifier (e.g., 'starter', 'growth')
     * @param  string  $priceKey  The price identifier (e.g., 'monthly', 'yearly')
     * @param  int  $quantity  Number of units for the subscription
     * @param  string  $successUrl  URL to redirect on successful payment
     * @param  string  $cancelUrl  URL to redirect if user cancels
     * @param  Discount|null  $discount  Optional discount to apply
     */
    public function __construct(
        public User $user,
        public string $planKey,
        public string $priceKey,
        public int $quantity,
        public string $successUrl,
        public string $cancelUrl,
        public ?Discount $discount = null,
    ) {}

    /**
     * Get metadata array for the checkout session.
     *
     * @return array<string, string>
     */
    public function metadata(): array
    {
        $metadata = [
            'user_id' => (string) $this->user->id,
            'plan_key' => $this->planKey,
            'price_key' => $this->priceKey,
        ];

        if ($this->discount) {
            $metadata['discount_id'] = (string) $this->discount->id;
            $metadata['discount_code'] = $this->discount->code;
        }

        return $metadata;
    }

    /**
     * Determine payment mode based on plan type.
     *
     * @param  array<string, mixed>  $plan  The plan configuration
     * @return string 'payment' for one-time purchases, 'subscription' otherwise
     */
    public function resolveMode(array $plan): string
    {
        return ($plan['type'] ?? 'subscription') === 'one_time' ? 'payment' : 'subscription';
    }
}
