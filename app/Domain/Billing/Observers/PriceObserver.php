<?php

namespace App\Domain\Billing\Observers;

use App\Domain\Billing\Jobs\SyncPriceToProviders;
use App\Domain\Billing\Models\Price;

class PriceObserver
{
    /**
     * Handle the Price "created" event.
     */
    public function created(Price $price): void
    {
        SyncPriceToProviders::dispatch($price);
    }

    /**
     * Handle the Price "updated" event.
     */
    public function updated(Price $price): void
    {
        SyncPriceToProviders::dispatch($price);
    }
}
