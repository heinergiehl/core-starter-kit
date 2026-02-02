<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Adapters\Paddle\Handlers\PaddleSubscriptionHandler;
use App\Domain\Billing\Models\BillingCustomer;
use App\Enums\BillingProvider;
use App\Enums\SubscriptionStatus;
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
            'provider' => BillingProvider::Paddle->value,
            'provider_id' => 'cus_123',
            'email' => $user->email,
        ]);

        $handler = app(PaddleSubscriptionHandler::class);
        $payload = [
            'id' => 'sub_123',
            'status' => SubscriptionStatus::Active->value,
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
            'provider' => BillingProvider::Paddle->value,
            'provider_id' => 'sub_123',
            'user_id' => $user->id,
        ]);
    }
}
