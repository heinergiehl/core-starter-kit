<?php

namespace App\Domain\Billing\Traits;

use App\Domain\Billing\Services\EntitlementService;
use App\Enums\Feature;

trait HasEntitlements
{
    public function entitledTo(Feature $feature): mixed
    {
        return app(EntitlementService::class)->forUser($this)->value($feature);
    }

    /**
     * Check if user has access to a boolean feature.
     */
    public function canAccess(Feature $feature): bool
    {
        return (bool) $this->entitledTo($feature);
    }

    /**
     * Check if user has sufficient quota for a numeric feature.
     */
    public function hasQuota(Feature $feature, int $required = 1): bool
    {
        $limit = $this->entitledTo($feature);

        if ($limit === null) {
            return false;
        }

        // -1 or similar convention could mean unlimited, but for now assuming positive integers from billing
        return (int) $limit >= $required;
    }
}
