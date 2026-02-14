<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Adapters\Stripe\Handlers\StripeOrderHandler;
use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\WebhookEvent;
use App\Models\User;
use App\Notifications\PaymentSuccessfulNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class StripeOrderHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_async_payment_succeeded_marks_order_paid_and_sends_single_success_notification(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $handler = app(StripeOrderHandler::class);
        $event = new WebhookEvent([
            'type' => 'checkout.session.async_payment_succeeded',
            'payload' => [
                'type' => 'checkout.session.async_payment_succeeded',
            ],
        ]);

        $payload = [
            'id' => 'cs_async_123',
            'mode' => 'payment',
            'payment_intent' => 'pi_async_123',
            'payment_status' => 'paid',
            'amount_total' => 4900,
            'currency' => 'usd',
            'metadata' => [
                'user_id' => (string) $user->id,
                'plan_key' => 'lifetime',
                'price_key' => 'once',
            ],
        ];

        $handler->handle($event, $payload);
        $handler->handle($event, $payload);

        $this->assertDatabaseHas('orders', [
            'provider' => 'stripe',
            'provider_id' => 'pi_async_123',
            'user_id' => $user->id,
            'plan_key' => 'lifetime',
            'status' => 'paid',
            'amount' => 4900,
            'currency' => 'USD',
        ]);

        $order = Order::query()
            ->where('provider', 'stripe')
            ->where('provider_id', 'pi_async_123')
            ->firstOrFail();

        $this->assertNotNull($order->paid_at);
        $this->assertNotNull($order->payment_success_email_sent_at);
        $this->assertCount(1, Notification::sent($user, PaymentSuccessfulNotification::class));
    }
}

