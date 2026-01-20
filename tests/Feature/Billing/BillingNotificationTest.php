<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Adapters\Paddle\Handlers\PaddleSubscriptionHandler;
use App\Domain\Billing\Adapters\Stripe\Handlers\StripeSubscriptionHandler;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Organization\Models\Team;
use App\Models\User;
use App\Notifications\SubscriptionCancelledNotification;
use App\Notifications\SubscriptionStartedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BillingNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_stripe_subscription_activation_sends_welcome_notification_once()
    {
        Notification::fake();

        $user = User::factory()->create();
        $team = Team::factory()->create([
             'owner_id' => $user->id,
        ]);
        
        $user->current_team_id = $team->id;
        $user->save();

        // Simulate Stripe webhook payload for new subscription
        $handler = new StripeSubscriptionHandler();
        $payload = [
            'id' => 'sub_123',
            'customer' => 'cus_123',
            'status' => 'active',
            'items' => [
                'data' => [
                    [
                        'id' => 'si_123',
                        'price' => [
                            'id' => 'price_123',
                            'unit_amount' => 2900,
                        ],
                        'quantity' => 1,
                    ]
                ]
            ],
            'currency' => 'usd',
            'metadata' => [
                'team_id' => $team->id,
                'plan_key' => 'pro',
            ]
        ];

        // 1. Initial Sync - Should send notification
        $handler->syncSubscription($payload, 'customer.subscription.created');

        Notification::assertSentTo(
            [$user],
            SubscriptionStartedNotification::class,
            function ($notification, $channels) {
                return $notification->toMail(new User)->amount === 2900;
            }
        );

        // Verify database state
        $subscription = Subscription::where('provider_id', 'sub_123')->first();
        $this->assertNotNull($subscription->welcome_email_sent_at);
        
        // Clear recorded notifications
        Notification::fake();

        // 2. Duplicate Sync - Should NOT send notification again
        $handler->syncSubscription($payload, 'customer.subscription.updated');

        Notification::assertNothingSent();
    }

    public function test_paddle_subscription_activation_sends_welcome_notification_once()
    {
        Notification::fake();

        $user = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $user->id]);

        $handler = new PaddleSubscriptionHandler();
        $payload = [
            'id' => 'sub_paddle_123',
            'status' => 'active',
            'custom_data' => [
                'team_id' => $team->id,
                'plan_key' => 'pro',
            ],
            'items' => [
                [
                    'price' => [
                        'unit_price' => ['amount' => 4900]
                    ]
                ]
            ],
            'currency_code' => 'USD'
        ];

        // 1. Initial Sync
        $handler->syncSubscription($payload);

        Notification::assertSentTo($user, SubscriptionStartedNotification::class);
        $this->assertNotNull(Subscription::first()->welcome_email_sent_at);

        // 2. Duplicate Sync
        Notification::fake();
        $handler->syncSubscription($payload);
        Notification::assertNothingSent();
    }

    public function test_cancellation_notification_is_sent_once()
    {
        Notification::fake();

        $user = User::factory()->create();
        $team = Team::factory()->create(['owner_id' => $user->id]);
        
        // Create active subscription
        $subscription = Subscription::factory()->create([
            'team_id' => $team->id,
            'provider' => 'stripe',
            'provider_id' => 'sub_cancel_test',
            'status' => 'active',
            // Simulate welcome email already sent
            'welcome_email_sent_at' => now(), 
        ]);

        $handler = new StripeSubscriptionHandler();
        $payload = [
            'id' => 'sub_cancel_test',
            'customer' => 'cus_123',
            'status' => 'canceled',
            'canceled_at' => now()->timestamp,
            'metadata' => [
                'team_id' => $team->id,
                'plan_key' => 'pro',
            ]
        ];

        // 1. Sync Cancellation
        $handler->syncSubscription($payload, 'customer.subscription.deleted');

        Notification::assertSentTo($user, SubscriptionCancelledNotification::class);
        
        $subscription->refresh();
        $this->assertNotNull($subscription->cancellation_email_sent_at);
        $this->assertEquals('canceled', $subscription->status);

        // 2. Duplicate Sync
        Notification::fake();
        $handler->syncSubscription($payload, 'customer.subscription.deleted');
        Notification::assertNothingSent();
    }
}
