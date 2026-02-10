<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Data\SubscriptionData;
use App\Domain\Billing\Events\Subscription\SubscriptionCancelled;
use App\Domain\Billing\Events\Subscription\SubscriptionPlanChanged;
use App\Domain\Billing\Events\Subscription\SubscriptionResumed;
use App\Domain\Billing\Events\Subscription\SubscriptionStarted;
use App\Domain\Billing\Events\Subscription\SubscriptionTrialStarted;
use App\Domain\Billing\Exceptions\BillingException;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\BillingPlanService;
use App\Enums\BillingProvider;
use App\Enums\PaymentMode;
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
            throw new BillingException(
                __('No active subscription found. Please subscribe first.'),
                'BILLING_NO_ACTIVE_SUBSCRIPTION'
            );
        }

        // Can't change plan if pending cancellation
        if ($subscription->canceled_at) {
            throw new BillingException(
                __('Please resume your subscription before changing plans.'),
                'BILLING_SUBSCRIPTION_PENDING_CANCELLATION'
            );
        }

        try {
            $targetPlan = $this->planService->plan($planKey);
        } catch (\RuntimeException) {
            throw BillingException::unknownPlan($planKey);
        }

        $targetPrice = $targetPlan->getPrice($priceKey);
        if (! $targetPrice) {
            throw BillingException::unknownPrice($planKey, $priceKey);
        }

        if ($targetPlan->isOneTime() || $targetPrice->mode() === PaymentMode::OneTime) {
            throw new BillingException(
                __('You can only switch between recurring subscription plans.'),
                'BILLING_PLAN_CHANGE_INVALID_TARGET'
            );
        }

        // Get new price ID
        $newPriceId = $this->planService->providerPriceId(
            $subscription->provider->value,
            $planKey,
            $priceKey
        );

        if (! $newPriceId) {
            throw new BillingException(
                __('This plan is not available for your current provider.'),
                'BILLING_PROVIDER_PRICE_UNAVAILABLE'
            );
        }

        $currentProviderPriceId = $this->resolveCurrentProviderPriceId($subscription);
        if ($currentProviderPriceId && hash_equals($currentProviderPriceId, $newPriceId)) {
            throw new BillingException(
                __('You are already on this plan and billing interval.'),
                'BILLING_PLAN_ALREADY_ACTIVE'
            );
        }

        $previousPlanKey = $subscription->plan_key;

        $this->providerManager->adapter($subscription->provider->value)
            ->updateSubscription($subscription, $newPriceId);

        $metadata = $subscription->metadata ?? [];
        $metadata['plan_key'] = $planKey;
        $metadata['price_key'] = $priceKey;

        if ($subscription->provider === BillingProvider::Stripe) {
            $metadata['stripe_price_id'] = $newPriceId;
        }

        if ($subscription->provider === BillingProvider::Paddle) {
            $metadata['paddle_price_id'] = $newPriceId;
        }

        $subscription->update([
            'plan_key' => $planKey,
            'metadata' => $metadata,
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

            return $plan->name ?: ucfirst($planKey);
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

        $normalizedStatus = $this->normalizeSubscriptionStatus($data->status);

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
                'status' => $normalizedStatus,
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

    private function resolveCurrentProviderPriceId(Subscription $subscription): ?string
    {
        $metadata = $subscription->metadata ?? [];

        return match ($subscription->provider) {
            BillingProvider::Stripe => data_get($metadata, 'stripe_price_id')
                ?? data_get($metadata, 'items.data.0.price.id')
                ?? data_get($metadata, 'metadata.stripe_price_id'),
            BillingProvider::Paddle => data_get($metadata, 'paddle_price_id')
                ?? data_get($metadata, 'items.0.price.id')
                ?? data_get($metadata, 'items.0.price_id'),
            default => data_get($metadata, 'price_id'),
        };
    }

    private function normalizeSubscriptionStatus(string $status): string
    {
        $normalized = str_replace('-', '_', strtolower(trim($status)));

        return match ($normalized) {
            'active' => SubscriptionStatus::Active->value,
            'trial', 'trialing' => SubscriptionStatus::Trialing->value,
            'past_due' => SubscriptionStatus::PastDue->value,
            'cancelled', 'canceled' => SubscriptionStatus::Canceled->value,
            'unpaid' => SubscriptionStatus::Unpaid->value,
            'incomplete' => SubscriptionStatus::Incomplete->value,
            'incomplete_expired' => SubscriptionStatus::IncompleteExpired->value,
            'paused' => SubscriptionStatus::Paused->value,
            default => SubscriptionStatus::Incomplete->value,
        };
    }
}
