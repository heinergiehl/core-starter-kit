<?php

namespace App\Domain\Billing\Observers;

use App\Domain\Billing\Jobs\SyncProductToProviders;
use App\Domain\Billing\Models\Product;

class ProductObserver
{
    private function shouldSync(): bool
    {
        return (bool) config('saas.billing.auto_sync_catalog_on_model_events', true);
    }

    /**
     * Handle the Product "created" event.
     */
    public function created(Product $product): void
    {
        if (! $this->shouldSync()) {
            return;
        }

        SyncProductToProviders::dispatch($product);
    }

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        if (! $this->shouldSync()) {
            return;
        }

        SyncProductToProviders::dispatch($product);
    }
}
