<?php

namespace App\Domain\Billing\Observers;

use App\Domain\Billing\Jobs\SyncProductToProviders;
use App\Domain\Billing\Models\Product;

class ProductObserver
{
    /**
     * Handle the Product "created" event.
     */
    public function created(Product $product): void
    {
        SyncProductToProviders::dispatch($product);
    }

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        SyncProductToProviders::dispatch($product);
    }
}
