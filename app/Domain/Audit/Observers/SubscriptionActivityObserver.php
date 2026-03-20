<?php

namespace App\Domain\Audit\Observers;

use App\Domain\Audit\Services\ActivityLogService;
use App\Domain\Billing\Models\Subscription;

class SubscriptionActivityObserver
{
    public function __construct(
        private readonly ActivityLogService $activityLogService
    ) {}

    public function created(Subscription $subscription): void
    {
        $this->activityLogService->log(
            category: 'billing',
            event: 'billing.subscription_created',
            subject: $subscription,
            description: "Subscription created with status {$subscription->status->value}.",
            metadata: [
                'customer_id' => $subscription->user_id,
                'provider' => $subscription->provider?->value,
                'provider_id' => $subscription->provider_id,
                'plan_key' => $subscription->plan_key,
                'status' => $subscription->status->value,
                'quantity' => $subscription->quantity,
            ],
        );
    }

    public function updated(Subscription $subscription): void
    {
        $fromStatus = $this->normalizeEnumValue($subscription->getOriginal('status'));

        if ($subscription->wasChanged('status')) {
            $this->activityLogService->log(
                category: 'billing',
                event: 'billing.subscription_status_changed',
                subject: $subscription,
                description: sprintf(
                    'Subscription status changed from %s to %s.',
                    $fromStatus ?? 'unknown',
                    $subscription->status->value,
                ),
                metadata: [
                    'customer_id' => $subscription->user_id,
                    'provider' => $subscription->provider?->value,
                    'provider_id' => $subscription->provider_id,
                    'plan_key' => $subscription->plan_key,
                    'from_status' => $fromStatus,
                    'to_status' => $subscription->status->value,
                ],
            );
        }

        if ($subscription->wasChanged('plan_key')) {
            $this->activityLogService->log(
                category: 'billing',
                event: 'billing.subscription_plan_changed',
                subject: $subscription,
                description: sprintf(
                    'Subscription plan changed from %s to %s.',
                    $subscription->getOriginal('plan_key') ?? 'unknown',
                    $subscription->plan_key,
                ),
                metadata: [
                    'customer_id' => $subscription->user_id,
                    'provider' => $subscription->provider?->value,
                    'provider_id' => $subscription->provider_id,
                    'from_plan_key' => $subscription->getOriginal('plan_key'),
                    'to_plan_key' => $subscription->plan_key,
                ],
            );
        }
    }

    public function deleted(Subscription $subscription): void
    {
        $this->activityLogService->log(
            category: 'billing',
            event: 'billing.subscription_deleted',
            subject: $subscription,
            description: 'Subscription deleted.',
            metadata: [
                'customer_id' => $subscription->user_id,
                'provider' => $subscription->provider?->value,
                'provider_id' => $subscription->provider_id,
                'plan_key' => $subscription->plan_key,
                'status' => $subscription->status->value,
            ],
        );
    }

    private function normalizeEnumValue(mixed $value): mixed
    {
        return $value instanceof \BackedEnum ? $value->value : $value;
    }
}
