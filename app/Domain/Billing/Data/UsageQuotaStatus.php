<?php

namespace App\Domain\Billing\Data;

use App\Enums\UsageLimitBehavior;

readonly class UsageQuotaStatus
{
    public function __construct(
        public string $meterName,
        public string $meterKey,
        public string $unitLabel,
        public ?int $includedUnits,
        public int $usedUnits,
        public int $pendingUnits,
        public ?int $remainingUnits,
        public ?int $remainingUnitsAfterPending,
        public UsageLimitBehavior $behavior,
        public bool $allowsOverageBilling,
        public bool $blocksUsage,
        public bool $wouldExceedIncludedUsage,
    ) {}

    public function limitReached(): bool
    {
        return $this->includedUnits !== null
            && $this->remainingUnits !== null
            && $this->remainingUnits <= 0;
    }

    public function wouldBlockPendingUsage(): bool
    {
        return $this->blocksUsage && $this->wouldExceedIncludedUsage;
    }
}
