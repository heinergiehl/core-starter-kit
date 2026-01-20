<?php

namespace App\Domain\Billing\Adapters\Paddle\Concerns;

use App\Domain\Billing\Services\BillingPlanService;
use App\Domain\Organization\Models\Team;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Shared methods for resolving Paddle webhook data.
 */
trait ResolvesPaddleData
{
    /**
     * Resolve team ID from webhook data.
     */
    protected function resolveTeamId(array $data): ?int
    {
        $teamId = data_get($data, 'custom_data.team_id')
            ?? data_get($data, 'metadata.team_id')
            ?? data_get($data, 'team_id');

        if (!$teamId) {
            return null;
        }

        $teamId = (int) $teamId;

        if (!Team::query()->whereKey($teamId)->exists()) {
            Log::warning('Paddle webhook references missing team', [
                'team_id' => $teamId,
                'event_id' => data_get($data, 'id') ?? data_get($data, 'subscription_id') ?? data_get($data, 'transaction_id'),
            ]);

            return null;
        }

        return $teamId;
    }

    /**
     * Resolve plan key from webhook data.
     */
    protected function resolvePlanKey(array $data): ?string
    {
        $planKey = data_get($data, 'custom_data.plan_key')
            ?? data_get($data, 'metadata.plan_key')
            ?? data_get($data, 'plan_key');

        if ($planKey) {
            return (string) $planKey;
        }

        $priceId = data_get($data, 'items.0.price_id') ?? data_get($data, 'price_id');

        if (!$priceId) {
            return null;
        }

        return app(BillingPlanService::class)
            ->resolvePlanKeyByProviderId('paddle', $priceId);
    }

    /**
     * Convert Paddle timestamp to Carbon instance.
     */
    protected function timestampToDateTime(?string $timestamp): ?Carbon
    {
        if (!$timestamp) {
            return null;
        }

        if (is_numeric($timestamp)) {
            return now()->setTimestamp((int) $timestamp);
        }

        return Carbon::parse($timestamp);
    }
}
