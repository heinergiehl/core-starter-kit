<?php

namespace App\Domain\Billing\Data;

use Carbon\CarbonInterface;

readonly class UsageSummary
{
    public function __construct(
        public string $meterName,
        public string $meterKey,
        public string $unitLabel,
        public ?int $includedUnits,
        public int $usedUnits,
        public ?int $remainingUnits,
        public int $overageUnits,
        public int $packageSize,
        public ?int $overageAmountMinor,
        public int $billablePackages,
        public int $estimatedOverageAmountMinor,
        public string $currency,
        public string $roundingMode,
        public CarbonInterface $cycleStartsAt,
        public CarbonInterface $cycleEndsAt,
        public string $intervalLabel,
    ) {}
}
