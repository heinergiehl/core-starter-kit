<?php

namespace App\Domain\Billing\Listeners;

use App\Domain\Billing\Events\Subscription\SubscriptionCancelled;
use App\Domain\Billing\Events\Subscription\SubscriptionPlanChanged;
use App\Domain\Billing\Events\Subscription\SubscriptionResumed;
use App\Domain\Billing\Events\Subscription\SubscriptionStarted;
use App\Domain\Billing\Events\Subscription\SubscriptionTrialStarted;
use App\Domain\Billing\Services\BillingPlanService;
use App\Notifications\SubscriptionCancelledNotification;
use App\Notifications\SubscriptionPlanChangedNotification;
use App\Notifications\SubscriptionResumedNotification;
use App\Notifications\SubscriptionStartedNotification;
use App\Notifications\SubscriptionTrialStartedNotification;
use Illuminate\Support\Facades\Log;

class SendSubscriptionNotifications
{
    public function handleSubscriptionStarted(SubscriptionStarted $event): void
    {
        $subscription = $event->subscription;
        $owner = $subscription->user;

        if (! $owner) {
            return;
        }

        if (app()->environment('local')) {
            Log::info('SendSubscriptionNotifications: Sending Welcome/Started');
        }

        $owner->notify(new SubscriptionStartedNotification(
            planName: ucfirst($subscription->plan_key),
            amount: $event->amount,
            currency: $event->currency,
        ));
    }

    public function handleSubscriptionTrialStarted(SubscriptionTrialStarted $event): void
    {
        $subscription = $event->subscription;
        $owner = $subscription->user;

        if (! $owner) {
            return;
        }

        if (app()->environment('local')) {
            Log::info('SendSubscriptionNotifications: Sending Trial Started');
        }

        $owner->notify(new SubscriptionTrialStartedNotification(
            planName: ucfirst($subscription->plan_key),
            trialEndsAt: $subscription->trial_ends_at?->format('F j, Y'),
        ));

        if (! $owner->hasVerifiedEmail()) {
            $owner->sendEmailVerificationNotification();
        }
    }

    public function handleSubscriptionCancelled(SubscriptionCancelled $event): void
    {
        $subscription = $event->subscription;
        $owner = $subscription->user;

        if (! $owner) {
            return;
        }

        if (app()->environment('local')) {
            Log::info('SendSubscriptionNotifications: Sending Cancelled');
        }

        $owner->notify(new SubscriptionCancelledNotification(
            planName: ucfirst($subscription->plan_key),
            accessUntil: $subscription->ends_at?->format('F j, Y'),
        ));
    }

    public function handleSubscriptionResumed(SubscriptionResumed $event): void
    {
        $subscription = $event->subscription;
        $owner = $subscription->user;

        if (! $owner) {
            return;
        }

        if (app()->environment('local')) {
            Log::info('SendSubscriptionNotifications: Sending Resumed');
        }

        $owner->notify(new SubscriptionResumedNotification(
            planName: ucfirst($subscription->plan_key),
            accessUntil: $subscription->ends_at?->format('F j, Y'),
        ));
    }

    public function handleSubscriptionPlanChanged(SubscriptionPlanChanged $event): void
    {
        $subscription = $event->subscription;
        $owner = $subscription->user;

        if (! $owner) {
            return;
        }

        $owner->notify(new SubscriptionPlanChangedNotification(
            previousPlanName: $this->resolvePlanName($event->previousPlanKey),
            newPlanName: $this->resolvePlanName($event->newPlanKey),
            effectiveDate: $subscription->renews_at?->format('F j, Y'),
        ));
    }

    public function subscribe($events): array
    {
        return [
            SubscriptionStarted::class => 'handleSubscriptionStarted',
            SubscriptionTrialStarted::class => 'handleSubscriptionTrialStarted',
            SubscriptionCancelled::class => 'handleSubscriptionCancelled',
            SubscriptionResumed::class => 'handleSubscriptionResumed',
            SubscriptionPlanChanged::class => 'handleSubscriptionPlanChanged',
        ];
    }

    private function resolvePlanName(?string $planKey): string
    {
        if (! $planKey) {
            return 'subscription';
        }

        try {
            $plan = app(BillingPlanService::class)->plan($planKey);

            return $plan->name ?: ucfirst($planKey);
        } catch (\Throwable) {
            return ucfirst($planKey);
        }
    }
}
