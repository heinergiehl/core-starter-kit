<?php

namespace App\Observers;

use App\Domain\Billing\Models\ProviderDeletionOutbox;
use App\Domain\Billing\Models\Price;
use Illuminate\Support\Facades\DB;

class PriceObserver
{
    /**
     * Handle the Price "deleting" event.
     */
    /**
     * Handle the Price "deleting" event.
     */
    public function deleting(Price $price): void
    {
        $price->loadMissing('mappings');

        foreach ($price->mappings as $mapping) {
            $outbox = ProviderDeletionOutbox::query()->firstOrCreate(
                [
                    'provider' => $mapping->provider,
                    'entity_type' => 'price',
                    'provider_id' => $mapping->provider_id,
                ],
                [
                    'status' => ProviderDeletionOutbox::STATUS_PENDING,
                    'attempts' => 0,
                ],
            );

            if ($outbox->status === ProviderDeletionOutbox::STATUS_COMPLETED) {
                continue;
            }

            DB::afterCommit(function () use ($outbox): void {
                \App\Jobs\ProcessProviderDeletionJob::dispatch($outbox->id);
            });
        }
    }
}
