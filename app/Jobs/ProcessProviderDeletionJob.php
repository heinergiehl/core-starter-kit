<?php

namespace App\Jobs;

use App\Domain\Billing\Models\ProviderDeletionOutbox;
use App\Domain\Billing\Services\BillingProviderManager;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessProviderDeletionJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    public $backoff = [60, 300, 900, 1800, 3600];

    public function __construct(public int $outboxId)
    {
    }

    public function handle(BillingProviderManager $billing): void
    {
        $outbox = ProviderDeletionOutbox::query()->find($this->outboxId);

        if (!$outbox || $outbox->status === ProviderDeletionOutbox::STATUS_COMPLETED) {
            return;
        }

        if (!$this->isValidProviderId($outbox->provider, $outbox->entity_type, $outbox->provider_id)) {
            Log::warning('[ProcessProviderDeletionJob] Skipping invalid provider id', [
                'outbox_id' => $outbox->id,
                'provider' => $outbox->provider,
                'entity_type' => $outbox->entity_type,
                'provider_id' => $outbox->provider_id,
            ]);

            $outbox->status = ProviderDeletionOutbox::STATUS_COMPLETED;
            $outbox->last_error = 'Skipped provider deletion due to invalid provider id.';
            $outbox->completed_at = now();
            $outbox->save();
            return;
        }

        if ($outbox->status === ProviderDeletionOutbox::STATUS_FAILED) {
            return;
        }

        $outbox->status = ProviderDeletionOutbox::STATUS_PROCESSING;
        $outbox->attempts++;
        $outbox->save();

        try {
            $adapter = $billing->adapter($outbox->provider);

            if ($outbox->entity_type === 'product') {
                $adapter->archiveProduct($outbox->provider_id);
            } elseif ($outbox->entity_type === 'price') {
                $adapter->archivePrice($outbox->provider_id);
            } else {
                Log::warning('[ProcessProviderDeletionJob] Unknown entity type', [
                    'outbox_id' => $outbox->id,
                    'entity_type' => $outbox->entity_type,
                ]);
            }

            $outbox->status = ProviderDeletionOutbox::STATUS_COMPLETED;
            $outbox->last_error = null;
            $outbox->completed_at = now();
            $outbox->save();
        } catch (Throwable $e) {
            $outbox->last_error = $e->getMessage();
            $outbox->save();

            throw $e;
        }
    }

    public function failed(Throwable $e): void
    {
        $outbox = ProviderDeletionOutbox::query()->find($this->outboxId);

        if (!$outbox) {
            return;
        }

        $outbox->status = ProviderDeletionOutbox::STATUS_FAILED;
        $outbox->last_error = $e->getMessage();
        $outbox->save();
    }

    private function isValidProviderId(string $provider, string $entityType, ?string $providerId): bool
    {
        if (!$providerId) {
            return false;
        }

        $provider = strtolower($provider);
        $entityType = strtolower($entityType);

        return match ($provider) {
            'lemonsqueezy' => ctype_digit($providerId),
            'paddle' => $entityType === 'product'
                ? str_starts_with($providerId, 'pro_')
                : str_starts_with($providerId, 'pri_'),
            'stripe' => $entityType === 'product'
                ? str_starts_with($providerId, 'prod_')
                : str_starts_with($providerId, 'price_'),
            default => true,
        };
    }
}
