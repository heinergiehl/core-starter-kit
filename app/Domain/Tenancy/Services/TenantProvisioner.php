<?php

namespace App\Domain\Tenancy\Services;

use App\Domain\Organization\Models\Team;
use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Tenancy\Models\TenantDomain;

class TenantProvisioner
{
    public function ensureTenant(Team $team): Tenant
    {
        if ($team->tenant) {
            return $team->tenant;
        }

        $tenant = Tenant::create([
            'data' => [
                'team_id' => $team->id,
            ],
        ]);

        $team->tenant()->associate($tenant);
        $team->save();

        return $tenant;
    }

    public function syncDomainsForTeam(Team $team): void
    {
        $tenant = $this->ensureTenant($team);
        $domains = $this->desiredDomains($team);

        $query = TenantDomain::query()->where('tenant_id', $tenant->id);

        if ($domains) {
            $query->whereNotIn('domain', $domains);
        }

        $query->delete();

        foreach ($domains as $domain) {
            $existing = TenantDomain::query()->where('domain', $domain)->first();

            if ($existing) {
                if ($existing->tenant_id !== $tenant->id) {
                    continue;
                }

                continue;
            }

            TenantDomain::create([
                'domain' => $domain,
                'tenant_id' => $tenant->id,
            ]);
        }
    }

    /**
     * @return array<int, string>
     */
    private function desiredDomains(Team $team): array
    {
        $baseDomain = config('saas.tenancy.base_domain');
        $baseDomain = is_string($baseDomain) ? strtolower(trim($baseDomain)) : null;

        $domains = [];

        if ($baseDomain && $team->subdomain) {
            $subdomain = strtolower(trim($team->subdomain));

            if ($subdomain !== '') {
                $domains[] = "{$subdomain}.{$baseDomain}";
            }
        }

        if ($team->domain) {
            $customDomain = strtolower(trim($team->domain));

            if ($customDomain !== '') {
                $domains[] = $customDomain;
            }
        }

        return array_values(array_unique($domains));
    }
}
