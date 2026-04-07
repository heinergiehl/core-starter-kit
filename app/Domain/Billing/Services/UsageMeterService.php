<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Data\Price as BillingPrice;
use App\Domain\Billing\Data\UsageQuotaStatus;
use App\Domain\Billing\Data\UsageSummary;
use App\Domain\Billing\Exceptions\UsageQuotaExceededException;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\UsageRecord;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class UsageMeterService
{
    public function recordForPrice(
        User $user,
        BillingPrice $price,
        int $quantity = 1,
        ?Subscription $subscription = null,
        array $metadata = [],
        ?CarbonInterface $occurredAt = null,
        ?int $productId = null,
        ?int $priceId = null,
    ): UsageRecord {
        if (! $price->isMetered()) {
            throw new InvalidArgumentException('Usage can only be recorded for metered prices.');
        }

        $this->assertCanConsume($user, $price, $quantity, $subscription);

        return $this->record(
            user: $user,
            meterKey: (string) $price->usageMeterKey,
            quantity: $quantity,
            subscription: $subscription,
            planKey: $subscription?->plan_key,
            priceKey: $price->key,
            metadata: $metadata,
            occurredAt: $occurredAt,
            productId: $productId,
            priceId: $priceId,
        );
    }

    public function record(
        User $user,
        string $meterKey,
        int $quantity = 1,
        ?Subscription $subscription = null,
        ?string $planKey = null,
        ?string $priceKey = null,
        array $metadata = [],
        ?CarbonInterface $occurredAt = null,
        ?int $productId = null,
        ?int $priceId = null,
    ): UsageRecord {
        $resolvedMeterKey = trim($meterKey);

        if ($resolvedMeterKey === '') {
            throw new InvalidArgumentException('Usage meter key cannot be empty.');
        }

        if ($quantity <= 0) {
            throw new InvalidArgumentException('Usage quantity must be greater than zero.');
        }

        return UsageRecord::query()->create([
            'user_id' => $user->id,
            'subscription_id' => $subscription?->id,
            'product_id' => $productId,
            'price_id' => $priceId,
            'plan_key' => $planKey ?? $subscription?->plan_key,
            'price_key' => $priceKey ?? data_get($subscription?->metadata, 'price_key'),
            'meter_key' => $resolvedMeterKey,
            'quantity' => $quantity,
            'occurred_at' => ($occurredAt ?? now())->toDateTimeString(),
            'metadata' => $metadata,
        ]);
    }

    public function summaryFor(User $user, BillingPrice $price, ?Subscription $subscription = null): ?UsageSummary
    {
        if (! $price->isMetered()) {
            return null;
        }

        [$cycleStartsAt, $cycleEndsAt] = $this->resolveCycleWindow($price, $subscription);

        $usedUnits = (int) $this->usageRecordsQuery($user, $price, $subscription, $cycleStartsAt, $cycleEndsAt)
            ->sum('quantity');

        $includedUnits = $price->usageIncludedUnits;
        $remainingUnits = $includedUnits !== null
            ? max($includedUnits - $usedUnits, 0)
            : null;
        $overageUnits = $includedUnits !== null
            ? max($usedUnits - $includedUnits, 0)
            : $usedUnits;
        $packageSize = max($price->usagePackageSize ?? 1, 1);
        $billablePackages = $price->allowsOverageBilling()
            ? $this->resolveBillablePackages($overageUnits, $packageSize, $price->usageRoundingMode)
            : 0;
        $estimatedOverageAmountMinor = $price->allowsOverageBilling() && ($price->usageOverageAmount ?? 0) > 0
            ? $billablePackages * (int) $price->usageOverageAmount
            : 0;

        return new UsageSummary(
            meterName: $price->usageMeterName ?? 'Usage',
            meterKey: $price->usageMeterKey ?? '',
            unitLabel: $price->usageUnitLabel ?? 'unit',
            includedUnits: $includedUnits,
            usedUnits: $usedUnits,
            remainingUnits: $remainingUnits,
            overageUnits: $overageUnits,
            packageSize: $packageSize,
            overageAmountMinor: $price->usageOverageAmount,
            billablePackages: $billablePackages,
            estimatedOverageAmountMinor: $estimatedOverageAmountMinor,
            currency: strtoupper((string) $price->currency),
            roundingMode: $price->usageRoundingMode ?? 'up',
            cycleStartsAt: $cycleStartsAt,
            cycleEndsAt: $cycleEndsAt,
            intervalLabel: $this->intervalLabel($price)
        );
    }

    /**
     * @return Collection<int, UsageRecord>
     */
    public function historyFor(User $user, BillingPrice $price, ?Subscription $subscription = null, int $limit = 8): Collection
    {
        if (! $price->isMetered()) {
            return collect();
        }

        [$cycleStartsAt, $cycleEndsAt] = $this->resolveCycleWindow($price, $subscription);

        return $this->usageRecordsQuery($user, $price, $subscription, $cycleStartsAt, $cycleEndsAt)
            ->latest('occurred_at')
            ->limit(max($limit, 1))
            ->get();
    }

    public function quotaStatusFor(
        User $user,
        BillingPrice $price,
        int $pendingQuantity = 0,
        ?Subscription $subscription = null,
    ): ?UsageQuotaStatus {
        $summary = $this->summaryFor($user, $price, $subscription);

        if (! $summary) {
            return null;
        }

        $pendingUnits = max($pendingQuantity, 0);
        $includedUnits = $summary->includedUnits;
        $remainingUnitsAfterPending = $includedUnits !== null
            ? max($includedUnits - ($summary->usedUnits + $pendingUnits), 0)
            : null;
        $wouldExceedIncludedUsage = $includedUnits !== null
            && ($summary->usedUnits + $pendingUnits) > $includedUnits;

        return new UsageQuotaStatus(
            meterName: $summary->meterName,
            meterKey: $summary->meterKey,
            unitLabel: $summary->unitLabel,
            includedUnits: $includedUnits,
            usedUnits: $summary->usedUnits,
            pendingUnits: $pendingUnits,
            remainingUnits: $summary->remainingUnits,
            remainingUnitsAfterPending: $remainingUnitsAfterPending,
            behavior: $price->usageLimitBehavior,
            allowsOverageBilling: $price->allowsOverageBilling(),
            blocksUsage: $price->blocksUsageAtLimit(),
            wouldExceedIncludedUsage: $wouldExceedIncludedUsage,
        );
    }

    public function canConsume(
        User $user,
        BillingPrice $price,
        int $quantity = 1,
        ?Subscription $subscription = null,
    ): bool {
        $quotaStatus = $this->quotaStatusFor($user, $price, $quantity, $subscription);

        if (! $quotaStatus) {
            return true;
        }

        return ! $quotaStatus->wouldBlockPendingUsage();
    }

    public function assertCanConsume(
        User $user,
        BillingPrice $price,
        int $quantity = 1,
        ?Subscription $subscription = null,
    ): void {
        $quotaStatus = $this->quotaStatusFor($user, $price, $quantity, $subscription);

        if ($quotaStatus && $quotaStatus->wouldBlockPendingUsage()) {
            throw UsageQuotaExceededException::fromStatus($quotaStatus);
        }
    }

    /**
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    private function resolveCycleWindow(BillingPrice $price, ?Subscription $subscription): array
    {
        $now = CarbonImmutable::now();
        $intervalCount = max($price->intervalCount, 1);

        if ($subscription?->renews_at) {
            $cycleEndsAt = CarbonImmutable::instance($subscription->renews_at);
        } elseif ($subscription?->ends_at && $subscription->ends_at->isFuture()) {
            $cycleEndsAt = CarbonImmutable::instance($subscription->ends_at);
        } else {
            $cycleEndsAt = $this->addInterval($now, $price->interval, $intervalCount);
        }

        $cycleStartsAt = $this->subtractInterval($cycleEndsAt, $price->interval, $intervalCount);

        return [$cycleStartsAt, $cycleEndsAt];
    }

    private function addInterval(CarbonImmutable $date, string $interval, int $intervalCount): CarbonImmutable
    {
        return match ($interval) {
            'day' => $date->addDays($intervalCount),
            'week' => $date->addWeeks($intervalCount),
            'year' => $date->addYears($intervalCount),
            default => $date->addMonths($intervalCount),
        };
    }

    private function subtractInterval(CarbonImmutable $date, string $interval, int $intervalCount): CarbonImmutable
    {
        return match ($interval) {
            'day' => $date->subDays($intervalCount),
            'week' => $date->subWeeks($intervalCount),
            'year' => $date->subYears($intervalCount),
            default => $date->subMonths($intervalCount),
        };
    }

    private function resolveBillablePackages(int $overageUnits, int $packageSize, ?string $roundingMode): int
    {
        if ($overageUnits <= 0) {
            return 0;
        }

        $resolvedRoundingMode = $roundingMode === 'down' ? 'down' : 'up';

        if ($packageSize <= 1) {
            return $overageUnits;
        }

        return $resolvedRoundingMode === 'down'
            ? (int) floor($overageUnits / $packageSize)
            : (int) ceil($overageUnits / $packageSize);
    }

    private function usageRecordsQuery(
        User $user,
        BillingPrice $price,
        ?Subscription $subscription,
        CarbonInterface $cycleStartsAt,
        CarbonInterface $cycleEndsAt,
    ): Builder {
        $query = UsageRecord::query()
            ->where('user_id', $user->id)
            ->where('meter_key', $price->usageMeterKey)
            ->whereBetween('occurred_at', [$cycleStartsAt, $cycleEndsAt]);

        if ($subscription) {
            $query->where('subscription_id', $subscription->id);
        } elseif (filled($price->key)) {
            $query->where(function (Builder $builder) use ($price): void {
                $builder
                    ->where('price_key', $price->key)
                    ->orWhereNull('price_key');
            });
        }

        return $query;
    }

    private function intervalLabel(BillingPrice $price): string
    {
        $intervalCount = max($price->intervalCount, 1);

        if ($intervalCount === 1) {
            return match ($price->interval) {
                'day' => 'day',
                'week' => 'week',
                'year' => 'year',
                default => 'month',
            };
        }

        return "{$intervalCount} {$price->interval}s";
    }
}
