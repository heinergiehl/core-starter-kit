<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Events\Subscription\SubscriptionCancelled;
use App\Domain\Billing\Events\Subscription\SubscriptionPlanChanged;
use App\Domain\Billing\Events\Subscription\SubscriptionResumed;
use App\Domain\Billing\Events\Subscription\SubscriptionStarted;
use App\Domain\Billing\Events\Subscription\SubscriptionTrialStarted;
use App\Domain\Billing\Models\Subscription;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    public function __construct(
        protected BillingProviderManager $providerManager
    ) {}

    public function syncFromProvider(
        string $provider,
        string $providerId,
        int $userId,
        string $planKey,
        string $status,
        int $quantity,
        array $dates,
        array $metadata
    ): Subscription {
        $existingSubscription = Subscription::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        // 1. Capture Old State
        $previousPlanKey = $existingSubscription?->plan_key;
        $wasCanceledOrGrace = $existingSubscription && ($existingSubscription->status === \App\Enums\SubscriptionStatus::Canceled || $existingSubscription->canceled_at);

        // 2. Update Database
        $subscription = Subscription::query()->updateOrCreate(
            [
                'provider' => $provider,
                'provider_id' => $providerId,
            ],
            [
                'user_id' => $userId,
                'plan_key' => $planKey,
                'status' => $status,
                'quantity' => max($quantity, 1),
                'trial_ends_at' => $dates['trial_ends_at'] ?? null,
                'renews_at' => $dates['renews_at'] ?? null,
                'ends_at' => $dates['ends_at'] ?? null,
                'canceled_at' => $dates['canceled_at'] ?? null,
                'metadata' => $metadata,
            ]
        );

        // 3. Handle Events
        $this->dispatchEvents($subscription, $previousPlanKey, $wasCanceledOrGrace);

        return $subscription;
    }

    public function cancel(Subscription $subscription): \Carbon\Carbon
    {
        $endsAt = $this->providerManager->adapter($subscription->provider->value)
            ->cancelSubscription($subscription);

        $subscription->update([
            'canceled_at' => now(),
            'ends_at' => $endsAt,
        ]);

        event(new SubscriptionCancelled($subscription));
        $subscription->forceFill(['cancellation_email_sent_at' => now()])->saveQuietly();

        return $endsAt;
    }

    public function resume(Subscription $subscription): void
    {
        $this->providerManager->adapter($subscription->provider->value)
            ->resumeSubscription($subscription);

        $subscription->update([
            'canceled_at' => null,
            'ends_at' => null,
        ]);

        event(new SubscriptionResumed($subscription));
        $subscription->forceFill(['cancellation_email_sent_at' => null])->saveQuietly();
    }

    protected function dispatchEvents(Subscription $subscription, ?string $previousPlanKey, bool $wasCanceledOrGrace): void
    {
        $isCanceledOrGrace = $subscription->status === \App\Enums\SubscriptionStatus::Canceled || $subscription->canceled_at;
        $nowActiveOrTrialing = in_array($subscription->status, [\App\Enums\SubscriptionStatus::Active, \App\Enums\SubscriptionStatus::Trialing], true);
        $resumed = $nowActiveOrTrialing && $wasCanceledOrGrace && ! $isCanceledOrGrace;

        // 3.1 Resumed
        if ($resumed && $subscription->cancellation_email_sent_at) {
            if (app()->environment('local')) {
                Log::info('SubscriptionService: Dispatching Resumed');
            }
            event(new SubscriptionResumed($subscription));
            $subscription->forceFill(['cancellation_email_sent_at' => null])->saveQuietly();
        }

        // 3.2 Plan Changed
        $planChanged = $previousPlanKey
            && $previousPlanKey !== $subscription->plan_key
            && $subscription->plan_key !== 'unknown';

        if ($planChanged) {
            event(new SubscriptionPlanChanged($subscription, $previousPlanKey, $subscription->plan_key));
        }

        // 3.3 Trial Started
        // Only if currently trialing AND we haven't sent the email yet.
        $onTrial = $subscription->status === \App\Enums\SubscriptionStatus::Trialing || $subscription->onTrial();
        if ($onTrial && ! $subscription->trial_started_email_sent_at) {
            if (app()->environment('local')) {
                Log::info('SubscriptionService: Dispatching Trial Started');
            }
            event(new SubscriptionTrialStarted($subscription));
            $subscription->forceFill(['trial_started_email_sent_at' => now()])->saveQuietly();
        }

        // 3.4 Active / Started (Welcome)
        if ($subscription->status === \App\Enums\SubscriptionStatus::Active
            && ! $onTrial
            && ! $subscription->welcome_email_sent_at
        ) {
            if (app()->environment('local')) {
                Log::info('SubscriptionService: Dispatching Welcome/Started');
            }

            // Resolve amount/currency from metadata or fallback
            // Metadata structure varies by provider, normalized in handler usually but best effort here
            $meta = $subscription->metadata ?? [];

            // Try Stripe/Paddle/LS patterns from metadata passed in
            $amount = data_get($meta, 'items.data.0.price.unit_amount')
               ?? data_get($meta, 'items.0.price.unit_price.amount') // Paddle
               ?? data_get($meta, 'total') // LS
               ?? 0;

            $currency = data_get($meta, 'currency')
               ?? data_get($meta, 'items.data.0.price.currency')
               ?? data_get($meta, 'currency_code')
               ?? 'USD';

            event(new SubscriptionStarted(
                $subscription,
                (int) $amount,
                strtoupper((string) $currency)
            ));

            $subscription->forceFill(['welcome_email_sent_at' => now()])->saveQuietly();
        }

        // 3.5 Cancelled
        if ($isCanceledOrGrace
            && ! $resumed
            && ! $subscription->cancellation_email_sent_at
        ) {
            if (app()->environment('local')) {
                Log::info('SubscriptionService: Dispatching Cancelled');
            }
            event(new SubscriptionCancelled($subscription));
            $subscription->forceFill(['cancellation_email_sent_at' => now()])->saveQuietly();
        }
    }
}
