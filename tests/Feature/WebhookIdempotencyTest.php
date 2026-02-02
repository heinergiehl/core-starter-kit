<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookIdempotencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        \App\Domain\Billing\Models\PaymentProvider::create([
            'slug' => 'paddle',
            'name' => 'Paddle',
            'is_active' => true,
            'configuration' => ['webhook_secret' => 'test'],
        ]);
    }

    public function test_webhook_is_idempotent(): void
    {
        $payload = [
            'id' => 'evt_123',
            'event_type' => 'subscription.created',
        ];

        $this->postJson('/webhooks/paddle', $payload)->assertNoContent();
        $this->postJson('/webhooks/paddle', $payload)->assertNoContent();

        $this->assertSame(1, WebhookEvent::count());
        $this->assertDatabaseHas('webhook_events', [
            'provider' => 'paddle',
            'event_id' => 'evt_123',
        ]);
    }
}
