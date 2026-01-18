<?php

namespace App\Domain\Organization\Services;

use App\Domain\Tenancy\Models\TenantDomain;
use Illuminate\Support\Str;

class TenantDomainValidator
{
    /**
     * @return string|null
     */
    public function validateSubdomain(?string $value): ?string
    {
        $value = $this->normalize($value);

        if ($value === null) {
            return null;
        }

        if (!preg_match('/^[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?$/', $value)) {
            return 'Subdomain contains invalid characters.';
        }

        if ($this->isReservedSubdomain($value)) {
            return 'This subdomain is reserved.';
        }

        return null;
    }

    public function subdomainInUse(?string $value, ?int $tenantId = null): bool
    {
        $value = $this->normalize($value);
        $baseDomain = $this->baseDomain();

        if (!$value || !$baseDomain) {
            return false;
        }

        return $this->domainInUse("{$value}.{$baseDomain}", $tenantId);
    }

    /**
     * @return string|null
     */
    public function validateDomain(?string $value): ?string
    {
        $value = $this->normalize($value);

        if ($value === null) {
            return null;
        }

        if (str_contains($value, '://') || str_contains($value, '/')) {
            return 'Custom domain must be a hostname only.';
        }

        if (!preg_match('/^(?:[a-z0-9-]+\.)+[a-z]{2,}$/i', $value)) {
            return 'Custom domain must be a valid hostname.';
        }

        $baseDomain = $this->baseDomain();

        if ($baseDomain && ($value === $baseDomain || str_ends_with($value, ".{$baseDomain}"))) {
            return 'Custom domain must not be part of the base domain.';
        }

        return null;
    }

    public function domainInUse(?string $value, ?int $tenantId = null): bool
    {
        $value = $this->normalize($value);

        if ($value === null) {
            return false;
        }

        $query = TenantDomain::query()->where('domain', $value);

        if ($tenantId) {
            $query->where('tenant_id', '!=', $tenantId);
        }

        return $query->exists();
    }

    public function normalize(?string $value): ?string
    {
        $value = $value !== null ? trim($value) : null;

        if ($value === '') {
            return null;
        }

        return Str::lower($value);
    }

    public function isReservedSubdomain(string $value): bool
    {
        $reserved = config('saas.tenancy.reserved_subdomains', []);

        return in_array($value, $reserved, true);
    }

    public function baseDomain(): ?string
    {
        $baseDomain = config('saas.tenancy.base_domain');

        if (!$baseDomain) {
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            $baseDomain = is_string($appHost) ? $appHost : null;
        }

        return $baseDomain ? Str::lower($baseDomain) : null;
    }
}
