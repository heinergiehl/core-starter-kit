<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Adapters\Paddle\Handlers\PaddleOrderHandler;
use App\Models\User;
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
}
