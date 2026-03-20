<?php

namespace App\Domain\Billing\Observers;

use App\Domain\Billing\Jobs\SyncPriceToProviders;
use App\Domain\Billing\Models\Price;

class PriceObserver
{
    private function shouldSync(): bool
    {
        return (bool) config('saas.billing.auto_sync_catalog_on_model_events', true);
    }

    /**
     * Handle the Price "created" event.
     */
    public function created(Price $price): void
    {
        if (! $this->shouldSync()) {
            return;
        }

        SyncPriceToProviders::dispatch($price);
    }

    /**
     * Handle the Price "updated" event.
     */
    public function updated(Price $price): void
    {
        if (! $this->shouldSync()) {
            return;
        }

        SyncPriceToProviders::dispatch($price);
    }
}
