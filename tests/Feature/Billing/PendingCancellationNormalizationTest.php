<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Adapters\Paddle\Handlers\PaddleSubscriptionHandler;
use App\Domain\Billing\Adapters\Stripe\Handlers\StripeSubscriptionHandler;
use App\Domain\Billing\Models\Subscription;
use App\Enums\BillingProvider;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PendingCancellationNormalizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stripe_cancel_at_period_end_sets_pending_cancellation_fields(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        $cancelAt = now()->addDays(7);
        $handler = app(StripeSubscriptionHandler::class);

        $handler->syncSubscription([
            'id' => 'sub_stripe_pending_cancel',
            'status' => SubscriptionStatus::Active->value,
            'cancel_at_period_end' => true,
            'cancel_at' => $cancelAt->timestamp,
            'current_period_end' => $cancelAt->timestamp,
            'metadata' => [
                'user_id' => (string) $user->id,
                'plan_key' => 'pro',
            ],
            'items' => [
                'data' => [
                    [
                        'id' => 'si_123',
                        'quantity' => 1,
                        'price' => [
                            'id' => 'price_pro_monthly',
                        ],
                    ],
                ],
            ],
        ], 'customer.subscription.updated');

        $subscription = Subscription::query()
            ->where('provider', BillingProvider::Stripe->value)
            ->where('provider_id', 'sub_stripe_pending_cancel')
            ->first();

        $this->assertNotNull($subscription);
        $this->assertNotNull($subscription->canceled_at);
        $this->assertNotNull($subscription->ends_at);
        $this->assertSame($cancelAt->timestamp, $subscription->ends_at?->timestamp);
    }

    public function test_paddle_scheduled_cancel_sets_pending_cancellation_fields(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        $effectiveAt = now()->addDays(10);
        $handler = app(PaddleSubscriptionHandler::class);

        $handler->syncSubscription([
            'id' => 'sub_paddle_pending_cancel',
            'status' => SubscriptionStatus::Active->value,
            'items' => [
                [
                    'quantity' => 1,
                    'price' => ['id' => 'pri_pro_monthly'],
                ],
            ],
            'scheduled_change' => [
                'action' => 'cancel',
                'effective_at' => $effectiveAt->toIso8601String(),
            ],
            'custom_data' => [
                'user_id' => $user->id,
                'plan_key' => 'pro',
            ],
        ]);

        $subscription = Subscription::query()
            ->where('provider', BillingProvider::Paddle->value)
            ->where('provider_id', 'sub_paddle_pending_cancel')
            ->first();

        $this->assertNotNull($subscription);
        $this->assertNotNull($subscription->canceled_at);
        $this->assertNotNull($subscription->ends_at);
        $this->assertSame(
            Carbon::parse($effectiveAt)->timestamp,
            $subscription->ends_at?->timestamp
        );
    }
}

