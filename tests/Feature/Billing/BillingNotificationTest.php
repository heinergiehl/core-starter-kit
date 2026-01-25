<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Adapters\Paddle\Handlers\PaddleSubscriptionHandler;
use App\Domain\Billing\Adapters\Stripe\Handlers\StripeInvoiceHandler;
use App\Domain\Billing\Adapters\Stripe\Handlers\StripeSubscriptionHandler;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\WebhookEvent;
use App\Models\User;
use App\Notifications\PaymentFailedNotification;
use App\Notifications\SubscriptionCancelledNotification;
use App\Notifications\SubscriptionPlanChangedNotification;
use App\Notifications\SubscriptionStartedNotification;
use App\Notifications\SubscriptionTrialStartedNotification;
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

        // Simulate Stripe webhook payload for new subscription
        $handler = app(StripeSubscriptionHandler::class);
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
                    ],
                ],
            ],
            'currency' => 'usd',
            'metadata' => [
                'user_id' => $user->id,
                'plan_key' => 'pro',
            ],
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

        $handler = app(PaddleSubscriptionHandler::class);
        $payload = [
            'id' => 'sub_paddle_123',
            'status' => 'active',
            'custom_data' => [
                'user_id' => $user->id,
                'plan_key' => 'pro',
            ],
            'items' => [
                [
                    'price' => [
                        'unit_price' => ['amount' => 4900],
                    ],
                ],
            ],
            'currency_code' => 'USD',
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

    public function test_paddle_subscription_trial_sends_trial_notification_once()
    {
        Notification::fake();

        $user = User::factory()->create();

        $handler = app(PaddleSubscriptionHandler::class);
        $payload = [
            'id' => 'sub_paddle_trial_123',
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(7)->timestamp,
            'custom_data' => [
                'user_id' => $user->id,
                'plan_key' => 'pro',
            ],
        ];

        $handler->syncSubscription($payload);

        Notification::assertSentTo($user, SubscriptionTrialStartedNotification::class);
        Notification::assertNotSentTo($user, SubscriptionStartedNotification::class);

        $this->assertNotNull(Subscription::first()->trial_started_email_sent_at);

        Notification::fake();
        $handler->syncSubscription($payload);
        Notification::assertNothingSent();
    }

    public function test_cancellation_notification_is_sent_once()
    {
        Notification::fake();

        $user = User::factory()->create();

        // Create active subscription
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'provider_id' => 'sub_cancel_test',
            'status' => 'active',
            // Simulate welcome email already sent
            'welcome_email_sent_at' => now(),
        ]);

        $handler = app(StripeSubscriptionHandler::class);
        $payload = [
            'id' => 'sub_cancel_test',
            'customer' => 'cus_123',
            'status' => 'canceled',
            'canceled_at' => now()->timestamp,
            'metadata' => [
                'user_id' => $user->id,
                'plan_key' => 'pro',
            ],
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

    public function test_plan_change_notification_is_sent_when_plan_changes()
    {
        Notification::fake();

        $user = User::factory()->create();

        Subscription::factory()->create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'provider_id' => 'sub_plan_change',
            'plan_key' => 'starter',
            'status' => 'active',
        ]);

        $handler = app(StripeSubscriptionHandler::class);
        $payload = [
            'id' => 'sub_plan_change',
            'customer' => 'cus_456',
            'status' => 'active',
            'items' => [
                'data' => [
                    [
                        'id' => 'si_456',
                        'price' => [
                            'id' => 'price_456',
                        ],
                    ],
                ],
            ],
            'metadata' => [
                'user_id' => $user->id,
                'plan_key' => 'pro',
            ],
        ];

        $handler->syncSubscription($payload, 'customer.subscription.updated');

        Notification::assertSentTo($user, SubscriptionPlanChangedNotification::class);

        Notification::fake();
        $handler->syncSubscription($payload, 'customer.subscription.updated');

        Notification::assertNothingSent();
    }

    public function test_invoice_payment_failed_sends_notification_once()
    {
        Notification::fake();

        $user = User::factory()->create();

        Subscription::factory()->create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'provider_id' => 'sub_fail_123',
            'plan_key' => 'pro',
            'status' => 'active',
        ]);

        $handler = new StripeInvoiceHandler;
        $event = new WebhookEvent([
            'type' => 'invoice.payment_failed',
        ]);

        $object = [
            'id' => 'in_fail_123',
            'subscription' => 'sub_fail_123',
            'status' => 'open',
            'amount_due' => 2900,
            'currency' => 'usd',
            'metadata' => [
                'user_id' => $user->id,
            ],
        ];

        $handler->handle($event, $object);

        Notification::assertSentTo($user, PaymentFailedNotification::class);

        $invoice = Invoice::query()
            ->where('provider', 'stripe')
            ->where('provider_id', 'in_fail_123')
            ->first();

        $this->assertNotNull($invoice?->payment_failed_email_sent_at);

        Notification::fake();
        $handler->handle($event, $object);
        Notification::assertNothingSent();
    }

    public function test_notifications_set_mail_recipient()
    {
        $user = User::factory()->create(['email' => 'customer@example.com']);

        $startedMail = (new SubscriptionStartedNotification)->toMail($user);
        $this->assertTrue($startedMail->hasTo('customer@example.com'));

        $cancelledMail = (new SubscriptionCancelledNotification)->toMail($user);
        $this->assertTrue($cancelledMail->hasTo('customer@example.com'));

        $planChangedMail = (new SubscriptionPlanChangedNotification)->toMail($user);
        $this->assertTrue($planChangedMail->hasTo('customer@example.com'));

        $failedMail = (new PaymentFailedNotification)->toMail($user);
        $this->assertTrue($failedMail->hasTo('customer@example.com'));

        $trialMail = (new SubscriptionTrialStartedNotification)->toMail($user);
        $this->assertTrue($trialMail->hasTo('customer@example.com'));
    }
}
