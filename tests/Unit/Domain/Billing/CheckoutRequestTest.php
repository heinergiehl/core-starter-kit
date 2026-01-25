<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Data\CheckoutRequest;
use App\Domain\Billing\Models\Discount;
use App\Models\User;
use Tests\TestCase;

class CheckoutRequestTest extends TestCase
{
    private function createMockUser(int $id = 1): User
    {
        $user = new User;
        $user->id = $id;
        $user->email = 'test@example.com';

        return $user;
    }

    public function test_metadata_contains_required_fields(): void
    {
        $request = new CheckoutRequest(
            user: $this->createMockUser(456),
            planKey: 'starter',
            priceKey: 'monthly',
            quantity: 1,
            successUrl: 'https://example.com/success',
            cancelUrl: 'https://example.com/cancel',
        );

        $metadata = $request->metadata();

        $this->assertEquals('456', $metadata['user_id']);
        $this->assertEquals('starter', $metadata['plan_key']);
        $this->assertEquals('monthly', $metadata['price_key']);
    }

    public function test_metadata_includes_discount_when_provided(): void
    {
        $discount = new Discount;
        $discount->id = 789;
        $discount->code = 'SAVE20';

        $request = new CheckoutRequest(
            user: $this->createMockUser(),
            planKey: 'growth',
            priceKey: 'yearly',
            quantity: 5,
            successUrl: 'https://example.com/success',
            cancelUrl: 'https://example.com/cancel',
            discount: $discount,
        );

        $metadata = $request->metadata();

        $this->assertEquals('789', $metadata['discount_id']);
        $this->assertEquals('SAVE20', $metadata['discount_code']);
    }

    public function test_resolve_mode_returns_payment_for_one_time(): void
    {
        $request = new CheckoutRequest(
            user: $this->createMockUser(),
            planKey: 'lifetime',
            priceKey: 'once',
            quantity: 1,
            successUrl: 'https://example.com/success',
            cancelUrl: 'https://example.com/cancel',
        );

        $plan = ['type' => 'one_time'];
        $mode = $request->resolveMode($plan);

        $this->assertEquals('payment', $mode);
    }

    public function test_resolve_mode_returns_subscription_for_subscription(): void
    {
        $request = new CheckoutRequest(
            user: $this->createMockUser(),
            planKey: 'starter',
            priceKey: 'monthly',
            quantity: 1,
            successUrl: 'https://example.com/success',
            cancelUrl: 'https://example.com/cancel',
        );

        $plan = ['type' => 'subscription'];
        $mode = $request->resolveMode($plan);

        $this->assertEquals('subscription', $mode);
    }

    public function test_resolve_mode_defaults_to_subscription(): void
    {
        $request = new CheckoutRequest(
            user: $this->createMockUser(),
            planKey: 'starter',
            priceKey: 'monthly',
            quantity: 1,
            successUrl: 'https://example.com/success',
            cancelUrl: 'https://example.com/cancel',
        );

        $plan = [];
        $mode = $request->resolveMode($plan);

        $this->assertEquals('subscription', $mode);
    }
}
