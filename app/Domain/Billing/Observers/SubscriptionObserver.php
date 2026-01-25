<?php

namespace App\Domain\Billing\Observers;

use App\Domain\Billing\Models\Subscription;
use Illuminate\Support\Facades\Log;

class SubscriptionObserver
{
    public function created(Subscription $subscription): void
    {
        if (app()->environment('local')) {
            Log::info('SubscriptionObserver: created event fired');
        }
        $this->handleCacheClearing($subscription);
    }

    public function updated(Subscription $subscription): void
    {
        $this->handleCacheClearing($subscription);
    }

    public function deleted(Subscription $subscription): void
    {
        $this->handleCacheClearing($subscription);
    }

    protected function handleCacheClearing(Subscription $subscription): void
    {
        if ($subscription->user_id) {
            \Illuminate\Support\Facades\Cache::forget("entitlements:user:{$subscription->user_id}");
        }
    }
}
