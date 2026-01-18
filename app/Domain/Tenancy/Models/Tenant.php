<?php

namespace App\Domain\Tenancy\Models;

use App\Domain\Organization\Models\Team;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

class Tenant extends BaseTenant
{
    use HasDomains;

    public $incrementing = true;

    protected $keyType = 'int';

    public function team(): HasOne
    {
        return $this->hasOne(Team::class, 'tenant_id');
    }
}
