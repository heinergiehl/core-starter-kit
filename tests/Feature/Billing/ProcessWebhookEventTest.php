<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Models\WebhookEvent;
use App\Domain\Billing\Services\BillingProviderManager;
use App\Jobs\ProcessWebhookEvent;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessWebhookEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_processing_event_is_released_for_later_retry_instead_of_nooping(): void
    {
        $event = WebhookEvent::query()->create([
            'provider' => 'stripe',
            'event_id' => 'evt_processing_fresh_release',
            'type' => 'checkout.session.completed',
            'payload' => [
                'id' => 'evt_processing_fresh_release',
                'type' => 'checkout.session.completed',
            ],
            'status' => 'processing',
            'received_at' => now(),
        ]);

        $manager = $this->mock(BillingProviderManager::class);
        $manager->shouldNotReceive('adapter');

        $queueJob = \Mockery::mock(Job::class);
        $queueJob->shouldReceive('release')
            ->once()
            ->withArgs(fn (int $delay): bool => $delay >= 5);

        $job = new ProcessWebhookEvent($event->id);
        $job->setJob($queueJob);
        $job->handle($manager);

        $this->assertDatabaseHas('webhook_events', [
            'id' => $event->id,
            'status' => 'processing',
        ]);
    }
}

