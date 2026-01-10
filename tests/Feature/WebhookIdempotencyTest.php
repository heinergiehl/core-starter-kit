<?php

namespace Tests\Feature;

use App\Domain\Billing\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WebhookIdempotencyTest extends TestCase
{
    use RefreshDatabase;

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
