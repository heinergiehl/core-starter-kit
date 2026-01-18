<?php

namespace App\Domain\Tenancy\Models;

use Stancl\Tenancy\Database\Models\Domain as BaseDomain;

class TenantDomain extends BaseDomain
{
    protected $table = 'domains';

    protected $fillable = [
        'domain',
        'tenant_id',
    ];
}
