<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Discount extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
        'description',
        'provider',
        'provider_id',
        'provider_type',
        'type',
        'amount',
        'currency',
        'max_redemptions',
        'redeemed_count',
        'starts_at',
        'ends_at',
        'is_active',
        'plan_keys',
        'price_keys',
        'metadata',
    ];

    protected $casts = [
        'amount' => 'int',
        'max_redemptions' => 'int',
        'redeemed_count' => 'int',
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'is_active' => 'bool',
        'plan_keys' => 'array',
        'price_keys' => 'array',
        'metadata' => 'array',
    ];

    public function redemptions(): HasMany
    {
        return $this->hasMany(DiscountRedemption::class);
    }

    public function setCodeAttribute(string $value): void
    {
        $this->attributes['code'] = strtoupper(trim($value));
    }
}
