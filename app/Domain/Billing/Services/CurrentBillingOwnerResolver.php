<?php

declare(strict_types=1);

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Contracts\BillingOwnerResolver as BillingOwnerResolverContract;
use App\Domain\Billing\Data\BillingOwner;
use App\Domain\Identity\Contracts\CurrentAccountResolver as CurrentAccountResolverContract;
use App\Models\User;

class CurrentBillingOwnerResolver implements BillingOwnerResolverContract
{
    public function __construct(
        private readonly CurrentAccountResolverContract $currentAccountResolver,
    ) {}

    public function current(): ?BillingOwner
    {
        $user = request()->user();

        return $user instanceof User ? $this->forUser($user) : null;
    }

    public function forUser(?User $user): ?BillingOwner
    {
        if (! $user) {
            return null;
        }

        $account = $this->currentAccountResolver->forUser($user) ?? $user->ensurePersonalAccount();
        $billingUserId = $account?->resolveBillingUserId();

        if ($account && $billingUserId) {
            return BillingOwner::forAccount($account, $billingUserId, $user);
        }

        return BillingOwner::forUser($user);
    }
}
