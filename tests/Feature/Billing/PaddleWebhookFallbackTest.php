<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Adapters\Paddle\Handlers\PaddleSubscriptionHandler;
use App\Domain\Billing\Models\BillingCustomer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaddleWebhookFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_paddle_subscription_falls_back_to_customer_mapping(): void
    {
        $user = User::factory()->create();

        BillingCustomer::query()->create([
            'user_id' => $user->id,
            'provider' => 'paddle',
            'provider_id' => 'cus_123',
            'email' => $user->email,
        ]);

        $handler = new PaddleSubscriptionHandler;
        $payload = [
            'id' => 'sub_123',
            'status' => 'active',
            'customer_id' => 'cus_123',
            'items' => [
                [
                    'price' => [
                        'unit_price' => ['amount' => 1000],
                    ],
                    'quantity' => 1,
                ],
            ],
        ];

        $handler->syncSubscription($payload);

        $this->assertDatabaseHas('subscriptions', [
            'provider' => 'paddle',
            'provider_id' => 'sub_123',
            'user_id' => $user->id,
        ]);
    }
}
