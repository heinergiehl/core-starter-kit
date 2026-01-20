<?php

namespace App\Domain\Billing\Models;

use App\Domain\Organization\Models\Team;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class BillingCustomer extends Model
{
    use HasFactory, BelongsToTenant;

    protected $guarded = [];

    protected $fillable = [
        'team_id',
        'provider',
        'provider_id',
        'email',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
