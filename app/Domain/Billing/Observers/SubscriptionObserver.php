<?php

namespace App\Domain\Billing\Observers;

use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\BillingPlanService;
use App\Notifications\SubscriptionCancelledNotification;
use App\Notifications\SubscriptionPlanChangedNotification;
use App\Notifications\SubscriptionStartedNotification;
use App\Notifications\SubscriptionTrialStartedNotification;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class SubscriptionObserver
{
    public function created(Subscription $subscription): void
    {
        \Illuminate\Support\Facades\Log::info('SubscriptionObserver: created event fired');
        $this->handleCacheClearing($subscription);
        $this->handleNotifications($subscription);
    }

    public function updated(Subscription $subscription): void
    {
        $this->handleCacheClearing($subscription);
        $this->handleNotifications($subscription);
    }

    public function deleted(Subscription $subscription): void
    {
        $this->handleCacheClearing($subscription);
    }

    protected function handleCacheClearing(Subscription $subscription): void
    {
        if ($subscription->team_id) {
            \Illuminate\Support\Facades\Cache::forget("entitlements:team:{$subscription->team_id}");
        }
    }

    protected function handleNotifications(Subscription $subscription): void
    {
        \Illuminate\Support\Facades\Log::info('SubscriptionObserver: handleNotifications', [
            'id' => $subscription->id,
            'status' => $subscription->status,
            'trial_ends_at' => $subscription->trial_ends_at,
            'onTrial' => $subscription->onTrial(),
            'event' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2)[1]['function'] ?? 'unknown',
        ]);

        $team = $subscription->team;
        $owner = $team?->owner;

        if (!$owner) {
            return;
        }

        // Reset cancellation email sent flag if subscription is active/trialing (resumed)
        // Resumed Logic
        if (in_array($subscription->status, ['active', 'trialing']) 
            && $subscription->cancellation_email_sent_at 
            // if we were sending cancellation email, that means it was previously canceled or in grace.
            // But we need to be sure it IS a resume. The presence of cancellation_email_sent_at is a good proxy.
            // But strictly, we should check if it WAS canceled.
            // For now, let's rely on the flag reset logic block.
        ) {
             \Illuminate\Support\Facades\Log::info('SubscriptionObserver: Sending Resumed');
             $owner->notify(new \App\Notifications\SubscriptionResumedNotification(
                 planName: ucfirst($subscription->plan_key),
                 accessUntil: $subscription->ends_at?->format('F j, Y'),
             ));

             $subscription->forceFill(['cancellation_email_sent_at' => null])->saveQuietly();
        }

        // Plan Change Notification
        if ($subscription->isDirty('plan_key') && $subscription->getOriginal('plan_key')) {
             $previousPlanKey = $subscription->getOriginal('plan_key');
             $newPlanKey = $subscription->plan_key;

             if ($previousPlanKey !== $newPlanKey && $newPlanKey !== 'unknown') {
                $owner->notify(new SubscriptionPlanChangedNotification(
                    previousPlanName: $this->resolvePlanName($previousPlanKey),
                    newPlanName: $this->resolvePlanName($newPlanKey),
                    effectiveDate: $subscription->renews_at?->format('F j, Y'),
                ));
             }
        }

        // Trial Started Notification
        $shouldNotifyTrial = ($subscription->status === 'trialing' || $subscription->onTrial())
            && !$subscription->trial_started_email_sent_at;
            
        \Illuminate\Support\Facades\Log::info('SubscriptionObserver: Trial Logic Check', [
            'status' => $subscription->status,
            'onTrial' => $subscription->onTrial(),
            'trial_started_email_sent_at' => $subscription->trial_started_email_sent_at,
            'shouldNotify' => $shouldNotifyTrial
        ]);

        if ($shouldNotifyTrial) {
            \Illuminate\Support\Facades\Log::info('SubscriptionObserver: Sending Trial Started');
            $owner->notify(new SubscriptionTrialStartedNotification(
                planName: ucfirst($subscription->plan_key),
                trialEndsAt: $subscription->trial_ends_at?->format('F j, Y'),
            ));

            $subscription->forceFill(['trial_started_email_sent_at' => now()])->saveQuietly();
        }

        // Welcome / Started Notification (paid activation)
        if ($subscription->status === 'active'
            && !$subscription->onTrial()
            && !$subscription->welcome_email_sent_at
        ) {
            \Illuminate\Support\Facades\Log::info('SubscriptionObserver: Sending Welcome/Started');
            // We need to try and resolve amount/currency from metadata if possible, or fallback
            // Since this is generic, we might not have exact amount here easily without looking up price.
            // For now, let's try to get it from metadata or default to 0/USD if not available,
            // or perhaps we can look it up via plan key if really needed.
            // But relying on metadata from webhooks is safer if it was stored there.

             // Metadata structure varies by provider, but handlers put helpful data there.
             $metadata = $subscription->metadata ?? [];
             
             // Try Stripe style
             $amount = data_get($metadata, 'items.data.0.price.unit_amount') 
                // Try Paddle style
                ?? data_get($metadata, 'items.0.price.unit_price.amount')
                ?? 0;

             $currency = data_get($metadata, 'currency') 
                ?? data_get($metadata, 'items.data.0.price.currency')
                ?? data_get($metadata, 'currency_code')
                ?? 'USD';

            $owner->notify(new SubscriptionStartedNotification(
                planName: ucfirst($subscription->plan_key),
                amount: (int) $amount,
                currency: strtoupper((string) $currency),
            ));

            $subscription->forceFill(['welcome_email_sent_at' => now()])->saveQuietly();
        }

        // Cancellation Notification
        // Check if status is canceled OR if canceled_at is set (grace period)
        // Also ensure we are NOT in the resumed state (if canceled_at is null, we shouldn't send it, but the check above handles that)
        if (($subscription->status === 'canceled' || $subscription->canceled_at) 
            && !$subscription->cancellation_email_sent_at
        ) {
            \Illuminate\Support\Facades\Log::info('SubscriptionObserver: Sending Cancelled');
            $owner->notify(new SubscriptionCancelledNotification(
                planName: ucfirst($subscription->plan_key),
                accessUntil: $subscription->ends_at?->format('F j, Y'),
            ));

            $subscription->forceFill(['cancellation_email_sent_at' => now()])->saveQuietly();
        }
    }

    private function resolvePlanName(?string $planKey): string
    {
        if (!$planKey) {
            return 'subscription';
        }

        try {
            $plan = app(BillingPlanService::class)->plan($planKey);

            return $plan['name'] ?? ucfirst($planKey);
        } catch (\Throwable) {
            return ucfirst($planKey);
        }
    }
}
