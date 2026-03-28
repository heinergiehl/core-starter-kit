<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Contracts\BillingOwnerResolver as BillingOwnerResolverContract;
use App\Domain\Billing\Data\BillingOwner;
use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Subscription;
use App\Enums\OrderStatus;
use App\Models\User;

class BillingAccessService
{
    public function __construct(
        private readonly BillingOwnerResolverContract $billingOwnerResolver,
    ) {}

    public function activeSubscriptionForOwner(BillingOwner $owner): ?Subscription
    {
        return $owner->applyToQuery(Subscription::query())
            ->isActive()
            ->latest('id')
            ->first();
    }

    public function activeSubscriptionForUser(User $user): ?Subscription
    {
        $owner = $this->billingOwnerResolver->forUser($user) ?? BillingOwner::forUser($user);

        return $this->activeSubscriptionForOwner($owner);
    }

    public function hasActiveSubscriptionForOwner(BillingOwner $owner): bool
    {
        return $this->activeSubscriptionForOwner($owner) !== null;
    }

    public function hasActiveSubscriptionForUser(User $user): bool
    {
        return $this->hasActiveSubscriptionForOwner(BillingOwner::forUser($user));
    }

    public function hasCompletedOrderForOwner(BillingOwner $owner): bool
    {
        return $owner->applyToQuery(Order::query())
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Completed->value])
            ->exists();
    }

    public function hasCompletedOrderForUser(User $user): bool
    {
        $owner = $this->billingOwnerResolver->forUser($user) ?? BillingOwner::forUser($user);

        return $this->hasCompletedOrderForOwner($owner);
    }

    public function hasBillingAccessForOwner(BillingOwner $owner): bool
    {
        return $this->hasActiveSubscriptionForOwner($owner)
            || $this->hasCompletedOrderForOwner($owner);
    }

    public function hasBillingAccessForUser(User $user): bool
    {
        $owner = $this->billingOwnerResolver->forUser($user) ?? BillingOwner::forUser($user);

        return $this->hasBillingAccessForOwner($owner);
    }
}
