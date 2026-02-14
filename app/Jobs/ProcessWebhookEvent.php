<?php

namespace App\Jobs;

use App\Domain\Billing\Models\WebhookEvent;
use App\Domain\Billing\Services\BillingProviderManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessWebhookEvent implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public int $timeout;

    public int $maxExceptions;

    public int $uniqueFor;

    public function __construct(private readonly int $eventId)
    {
        $this->tries = max(1, (int) config('saas.billing.webhooks.tries', 5));
        $this->timeout = max(30, (int) config('saas.billing.webhooks.timeout', 120));
        $this->maxExceptions = max(1, (int) config('saas.billing.webhooks.max_exceptions', 3));
        $this->uniqueFor = max(30, (int) config('saas.billing.webhooks.unique_for_seconds', 300));
    }

    public function uniqueId(): string
    {
        return 'billing-webhook:'.$this->eventId;
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        $default = [5, 15, 60];
        $configured = config('saas.billing.webhooks.backoff_seconds', $default);

        if (! is_array($configured) || $configured === []) {
            return $default;
        }

        $normalized = array_values(array_filter(
            array_map(static fn ($value): ?int => is_numeric($value) ? (int) $value : null, $configured),
            static fn (?int $value): bool => $value !== null && $value >= 0
        ));

        return $normalized !== [] ? $normalized : $default;
    }

    public function handle(BillingProviderManager $manager): void
    {
        $event = $this->claimEventForProcessing();

        if (! $event) {
            $this->deferIfFreshProcessing();

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

    private function claimEventForProcessing(): ?WebhookEvent
    {
        $staleCutoff = now()->subMinutes($this->processingStaleAfterMinutes());

        return DB::transaction(function () use ($staleCutoff): ?WebhookEvent {
            $event = WebhookEvent::query()
                ->lockForUpdate()
                ->find($this->eventId);

            if (! $event) {
                return null;
            }

            if ($event->status === 'processed') {
                return null;
            }

            if (
                $event->status === 'processing'
                && $event->updated_at
                && $event->updated_at->greaterThan($staleCutoff)
            ) {
                return null;
            }

            $event->update([
                'status' => 'processing',
                'error_message' => null,
            ]);

            return $event->fresh();
        });
    }

    private function deferIfFreshProcessing(): void
    {
        $event = WebhookEvent::query()->find($this->eventId);

        if (! $event || $event->status !== 'processing') {
            return;
        }

        $delaySeconds = $this->secondsUntilProcessingCanBeReclaimed($event);
        $this->release($delaySeconds);
    }

    private function processingStaleAfterMinutes(): int
    {
        return max(1, (int) config('saas.billing.webhooks.processing_stale_after_minutes', 15));
    }

    private function secondsUntilProcessingCanBeReclaimed(WebhookEvent $event): int
    {
        if (! $event->updated_at) {
            return 5;
        }

        $eligibleAt = $event->updated_at->copy()->addMinutes($this->processingStaleAfterMinutes());
        $seconds = now()->diffInSeconds($eligibleAt, false);

        if ($seconds <= 0) {
            return 5;
        }

        return min($seconds + 1, 3600);
    }
}
