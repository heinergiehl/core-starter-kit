<?php

namespace App\Observers;

use App\Domain\Billing\Models\ProviderDeletionOutbox;
use App\Domain\Billing\Models\Product;
use App\Jobs\ProcessProviderDeletionJob;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProductObserver
{
    /**
     * Handle the Product "deleting" event.
     */
    public function deleting(Product $product): void
    {
        $product->loadMissing(['providerMappings', 'prices.mappings']);

        foreach ($product->providerMappings as $mapping) {
            $outbox = ProviderDeletionOutbox::query()->firstOrCreate(
                [
                    'provider' => $mapping->provider,
                    'entity_type' => 'product',
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
                Log::info("ProductObserver: Queue provider deletion {$outbox->id}");
                ProcessProviderDeletionJob::dispatch($outbox->id);
            });
        }

        foreach ($product->prices as $price) {
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
                    Log::info("ProductObserver: Queue provider deletion {$outbox->id}");
                    ProcessProviderDeletionJob::dispatch($outbox->id);
                });
            }
        }
    }
}
