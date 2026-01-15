<?php

namespace Tests\Unit\Domain\Organization;

use App\Domain\Organization\Services\TenantDomainValidator;
use Tests\TestCase;

class TenantDomainValidatorTest extends TestCase
{
    public function test_valid_subdomain_passes(): void
    {
        config()->set('saas.tenancy.reserved_subdomains', ['app']);

        $validator = new TenantDomainValidator();

        $this->assertNull($validator->validateSubdomain('acme'));
    }

    public function test_reserved_subdomain_fails(): void
    {
        config()->set('saas.tenancy.reserved_subdomains', ['app']);

        $validator = new TenantDomainValidator();

        $this->assertNotNull($validator->validateSubdomain('app'));
    }

    public function test_invalid_subdomain_fails(): void
    {
        $validator = new TenantDomainValidator();

        $this->assertNotNull($validator->validateSubdomain('acme!'));
    }

    public function test_valid_custom_domain_passes(): void
    {
        config()->set('saas.tenancy.base_domain', 'example.com');

        $validator = new TenantDomainValidator();

        $this->assertNull($validator->validateDomain('acme.io'));
    }

    public function test_custom_domain_rejects_scheme(): void
    {
        $validator = new TenantDomainValidator();

        $this->assertNotNull($validator->validateDomain('https://acme.io'));
    }

    public function test_custom_domain_rejects_base_domain(): void
    {
        config()->set('saas.tenancy.base_domain', 'example.com');

        $validator = new TenantDomainValidator();

        $this->assertNotNull($validator->validateDomain('example.com'));
        $this->assertNotNull($validator->validateDomain('team.example.com'));
    }
}
