<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Adapters\Paddle\Handlers\PaddleOrderHandler;
use App\Domain\Billing\Models\Order;
use App\Models\User;
use App\Notifications\PaymentSuccessfulNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PaddleOrderHandlerTest extends TestCase
{
    use RefreshDatabase;

    public function test_sync_order_persists_one_time_transaction(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $handler = app(PaddleOrderHandler::class);

        $order = $handler->syncOrder([
            'id' => 'txn_one_time_123',
            'status' => 'completed',
            'currency_code' => 'USD',
            'details' => [
                'totals' => [
                    'grand_total' => 4900,
                    'subtotal' => 4900,
                    'tax' => 0,
                ],
            ],
            'items' => [
                [
                    'quantity' => 1,
                    'price' => [
                        'unit_price' => ['amount' => 4900],
                        'description' => 'Lifetime',
                    ],
                    'totals' => ['total' => 4900],
                ],
            ],
            'custom_data' => [
                'user_id' => $user->id,
                'plan_key' => 'lifetime',
                'price_key' => 'once',
            ],
        ], 'transaction.completed');

        $this->assertNotNull($order);

        $this->assertDatabaseHas('orders', [
            'provider' => 'paddle',
            'provider_id' => 'txn_one_time_123',
            'user_id' => $user->id,
            'plan_key' => 'lifetime',
            'status' => 'completed',
            'amount' => 4900,
            'currency' => 'USD',
        ]);
    }

    public function test_sync_order_sends_success_notification_once_for_duplicate_paid_webhooks(): void
    {
        Notification::fake();

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $handler = app(PaddleOrderHandler::class);
        $payload = [
            'id' => 'txn_one_time_dedupe_123',
            'status' => 'completed',
            'currency_code' => 'USD',
            'details' => [
                'totals' => [
                    'grand_total' => 4900,
                    'subtotal' => 4900,
                    'tax' => 0,
                ],
            ],
            'items' => [
                [
                    'quantity' => 1,
                    'price' => [
                        'unit_price' => ['amount' => 4900],
                        'description' => 'Lifetime',
                    ],
                    'totals' => ['total' => 4900],
                ],
            ],
            'custom_data' => [
                'user_id' => $user->id,
                'plan_key' => 'lifetime',
                'price_key' => 'once',
            ],
        ];

        $handler->syncOrder($payload, 'transaction.completed');
        $handler->syncOrder($payload, 'transaction.paid');

        $order = Order::query()
            ->where('provider', 'paddle')
            ->where('provider_id', 'txn_one_time_dedupe_123')
            ->firstOrFail();

        $this->assertNotNull($order->payment_success_email_sent_at);
        $this->assertCount(1, Notification::sent($user, PaymentSuccessfulNotification::class));
    }
}
