<?php

namespace App\Domain\RepoAccess\Listeners;

use App\Domain\Billing\Events\Subscription\SubscriptionStarted;
use App\Domain\RepoAccess\Services\RepoAccessService;
use App\Models\User;

class GrantRepoAccessOnPurchase
{
    public function __construct(
        private readonly RepoAccessService $repoAccessService,
    ) {}

    public function handleSubscriptionStarted(SubscriptionStarted $event): void
    {
        if (! $this->repoAccessService->isEnabled()) {
            return;
        }

        $userId = $event->subscription->user_id;

        if (! $userId) {
            return;
        }

        $user = User::query()->find($userId);

        if (! $user) {
            return;
        }

        $this->repoAccessService->queueGrant($user, 'subscription_started');
    }

    public function subscribe($events): array
    {
        return [
            SubscriptionStarted::class => 'handleSubscriptionStarted',
        ];
    }
}
