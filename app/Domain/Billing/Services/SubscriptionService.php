<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Data\SubscriptionData;
use App\Domain\Billing\Events\Subscription\SubscriptionCancelled;
use App\Domain\Billing\Events\Subscription\SubscriptionPlanChanged;
use App\Domain\Billing\Events\Subscription\SubscriptionResumed;
use App\Domain\Billing\Events\Subscription\SubscriptionStarted;
use App\Domain\Billing\Events\Subscription\SubscriptionTrialStarted;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\BillingPlanService;
use App\Enums\BillingProvider;
use App\Enums\SubscriptionStatus;
use App\Notifications\SubscriptionPlanChangedNotification;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class SubscriptionService
{
    public function __construct(
        protected BillingProviderManager $providerManager,
        protected BillingPlanService $planService,
    ) {}

    /**
     * Change subscription plan (upgrade/downgrade).
     */
    public function changePlan(User $user, string $planKey, string $priceKey): void
    {
        $subscription = $user->activeSubscription();

        if (! $subscription) {
            throw new \Exception(__('No active subscription found. Please subscribe first.'));
        }

        // Can't change plan if pending cancellation
        if ($subscription->canceled_at) {
            throw new \Exception(__('Please resume your subscription before changing plans.'));
        }

        // Get new price ID
        $newPriceId = $this->planService->providerPriceId(
            $subscription->provider->value,
            $planKey,
            $priceKey
        );

        if (! $newPriceId) {
            throw new \Exception(__('This plan is not available for your current provider.'));
        }

        $previousPlanKey = $subscription->plan_key;

        $this->providerManager->adapter($subscription->provider->value)
            ->updateSubscription($subscription, $newPriceId);

        $subscription->update([
            'plan_key' => $planKey,
        ]);

        $previousPlanName = $this->resolvePlanName($previousPlanKey);
        $newPlanName = $this->resolvePlanName($planKey);

        if ($previousPlanKey !== $planKey) {
            $user->notify(new SubscriptionPlanChangedNotification(
                previousPlanName: $previousPlanName,
                newPlanName: $newPlanName,
                effectiveDate: now()->format('F j, Y'),
            ));
        }
    }

    private function resolvePlanName(?string $planKey): string
    {
        if (! $planKey) {
            return 'subscription';
        }

        try {
            $plan = $this->planService->plan($planKey);

            return $plan['name'] ?? ucfirst($planKey);
        } catch (\Throwable) {
            return ucfirst($planKey);
        }
    }

    public function syncFromProvider(SubscriptionData $data): Subscription 
    {
        $existingSubscription = Subscription::query()
            ->where('provider', $data->provider)
            ->where('provider_id', $data->providerId)
            ->first();

        // 1. Capture Old State
        $previousPlanKey = $existingSubscription?->plan_key;
        $wasCanceledOrGrace = $existingSubscription && ($existingSubscription->status === SubscriptionStatus::Canceled || $existingSubscription->canceled_at);

        // 2. Update Database
        $subscription = Subscription::query()->updateOrCreate(
            [
                'provider' => $data->provider,
                'provider_id' => $data->providerId,
            ],
            [
                'user_id' => $data->userId,
                'plan_key' => $data->planKey,
                'status' => $data->status,
                'quantity' => max($data->quantity, 1),
                'trial_ends_at' => $data->trialEndsAt,
                'renews_at' => $data->renewsAt,
                'ends_at' => $data->endsAt,
                'canceled_at' => $data->canceledAt,
                'metadata' => $data->metadata,
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
        $isCanceledOrGrace = $subscription->status === SubscriptionStatus::Canceled || $subscription->canceled_at;
        $nowActiveOrTrialing = in_array($subscription->status, [SubscriptionStatus::Active, SubscriptionStatus::Trialing], true);
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
        $onTrial = $subscription->status === SubscriptionStatus::Trialing || $subscription->onTrial();
        if ($onTrial && ! $subscription->trial_started_email_sent_at) {
            if (app()->environment('local')) {
                Log::info('SubscriptionService: Dispatching Trial Started');
            }
            event(new SubscriptionTrialStarted($subscription));
            $subscription->forceFill(['trial_started_email_sent_at' => now()])->saveQuietly();
        }

        // 3.4 Active / Started (Welcome)
        if ($subscription->status === SubscriptionStatus::Active
            && ! $onTrial
            && ! $subscription->welcome_email_sent_at
        ) {
            if (app()->environment('local')) {
                Log::info('SubscriptionService: Dispatching Welcome/Started');
            }

            // Resolve amount/currency from metadata or fallback
            // Metadata structure varies by provider, normalized in handler usually but best effort here
            $meta = $subscription->metadata ?? [];

            $amount = $this->resolveSubscriptionAmount($meta);
            $currency = $this->resolveSubscriptionCurrency($meta);

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

    private function resolveSubscriptionAmount(array $meta): int
    {
        return data_get($meta, 'items.data.0.price.unit_amount')
            ?? data_get($meta, 'items.0.price.unit_price.amount') // Paddle
            ?? data_get($meta, 'total') // LS
            ?? 0;
    }

    private function resolveSubscriptionCurrency(array $meta): string
    {
        return data_get($meta, 'currency')
            ?? data_get($meta, 'items.data.0.price.currency')
            ?? data_get($meta, 'currency_code')
            ?? config('saas.billing.pricing.currency', 'USD');
    }
}
