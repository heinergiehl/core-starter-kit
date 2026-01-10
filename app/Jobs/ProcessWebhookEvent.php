<?php

namespace App\Jobs;

use App\Domain\Billing\Models\WebhookEvent;
use App\Domain\Billing\Services\BillingProviderManager;
use Throwable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessWebhookEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(private readonly int $eventId)
    {
    }

    public function handle(BillingProviderManager $manager): void
    {
        $event = WebhookEvent::find($this->eventId);

        if (!$event || $event->status === 'processed') {
            return;
        }

        try {
            $manager->adapter($event->provider)->processEvent($event);

            $event->update([
                'status' => 'processed',
                'processed_at' => now(),
                'error_message' => null,
            ]);
        } catch (Throwable $exception) {
            $event->update([
                'status' => 'failed',
                'error_message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
