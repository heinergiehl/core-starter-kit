<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Models\PaymentProvider;
use App\Domain\Billing\Models\WebhookEvent;
use App\Jobs\ProcessWebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WebhookRecoveryDispatchTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        PaymentProvider::query()->create([
            'slug' => 'paddle',
            'name' => 'Paddle',
            'is_active' => true,
            'configuration' => ['webhook_secret' => 'test'],
        ]);
    }

    public function test_failed_webhook_is_redispatched_and_reset(): void
    {
        Queue::fake();

        WebhookEvent::query()->create([
            'provider' => 'paddle',
            'event_id' => 'evt_recover_1',
            'type' => 'subscription.created',
            'payload' => [
                'id' => 'evt_recover_1',
                'event_type' => 'subscription.created',
                'data' => [],
            ],
            'status' => 'failed',
            'error_message' => 'temporary provider error',
            'received_at' => now()->subMinute(),
        ]);

        $this->postJson('/webhooks/paddle', [
            'id' => 'evt_recover_1',
            'event_type' => 'subscription.created',
            'data' => [],
        ])->assertNoContent();

        $this->assertDatabaseHas('webhook_events', [
            'provider' => 'paddle',
            'event_id' => 'evt_recover_1',
            'status' => 'received',
            'error_message' => null,
        ]);

        Queue::assertPushed(ProcessWebhookEvent::class, 1);
    }

    public function test_recent_processing_webhook_is_not_redispatched(): void
    {
        Queue::fake();

        $event = WebhookEvent::query()->create([
            'provider' => 'paddle',
            'event_id' => 'evt_processing_fresh',
            'type' => 'subscription.updated',
            'payload' => [
                'id' => 'evt_processing_fresh',
                'event_type' => 'subscription.updated',
                'data' => [],
            ],
            'status' => 'processing',
            'received_at' => now()->subMinute(),
        ]);
        WebhookEvent::query()->whereKey($event->id)->update(['updated_at' => now()]);

        $this->postJson('/webhooks/paddle', [
            'id' => 'evt_processing_fresh',
            'event_type' => 'subscription.updated',
            'data' => [],
        ])->assertNoContent();

        Queue::assertNothingPushed();
    }

    public function test_stale_processing_webhook_is_recovered_and_redispatched(): void
    {
        Queue::fake();

        $event = WebhookEvent::query()->create([
            'provider' => 'paddle',
            'event_id' => 'evt_processing_stale',
            'type' => 'subscription.updated',
            'payload' => [
                'id' => 'evt_processing_stale',
                'event_type' => 'subscription.updated',
                'data' => [],
            ],
            'status' => 'processing',
            'error_message' => 'stuck worker',
            'received_at' => now()->subHour(),
        ]);
        WebhookEvent::query()->whereKey($event->id)->update(['updated_at' => now()->subMinutes(30)]);

        $this->postJson('/webhooks/paddle', [
            'id' => 'evt_processing_stale',
            'event_type' => 'subscription.updated',
            'data' => [],
        ])->assertNoContent();

        $this->assertDatabaseHas('webhook_events', [
            'provider' => 'paddle',
            'event_id' => 'evt_processing_stale',
            'status' => 'received',
            'error_message' => null,
        ]);

        Queue::assertPushed(ProcessWebhookEvent::class, 1);
    }
}
