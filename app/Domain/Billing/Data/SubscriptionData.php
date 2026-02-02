<?php

namespace App\Domain\Billing\Data;

use Carbon\Carbon;

class SubscriptionData
{
    public function __construct(
        public string $provider,
        public string $providerId,
        public int $userId,
        public string $planKey,
        public string $status,
        public int $quantity,
        public ?Carbon $trialEndsAt = null,
        public ?Carbon $renewsAt = null,
        public ?Carbon $endsAt = null,
        public ?Carbon $canceledAt = null,
        public array $metadata = []
    ) {}

    public static function fromProvider(
        string $provider,
        string $providerId,
        int $userId,
        string $planKey,
        string $status,
        int $quantity,
        array $dates = [],
        array $metadata = []
    ): self {
        return new self(
            provider: $provider,
            providerId: $providerId,
            userId: $userId,
            planKey: $planKey,
            status: $status,
            quantity: $quantity,
            trialEndsAt: $dates['trial_ends_at'] ?? null,
            renewsAt: $dates['renews_at'] ?? null,
            endsAt: $dates['ends_at'] ?? null,
            canceledAt: $dates['canceled_at'] ?? null,
            metadata: $metadata
        );
    }
}
