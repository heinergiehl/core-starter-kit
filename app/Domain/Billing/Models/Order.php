<?php

namespace App\Domain\Billing\Models;

use App\Domain\Organization\Models\Team;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Order extends Model
{
    use HasFactory, BelongsToTenant;

    protected $fillable = [
        'team_id',
        'provider',
        'provider_id',
        'plan_key',
        'status',
        'amount',
        'currency',
        'paid_at',
        'refunded_at',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'int',
        'paid_at' => 'datetime',
        'refunded_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Get the product associated with this order via the plan_key.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'plan_key', 'key');
    }
}
