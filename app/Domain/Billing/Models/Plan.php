<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Domain\Billing\PlanFactory::new();
    }

    protected $fillable = [
        'product_id',
        'key',
        'name',
        'summary',
        'description',
        'type',
        'seat_based',
        'max_seats',
        'is_featured',
        'features',
        'entitlements',
        'is_active',
        'provider',
        'provider_id',
        'synced_at',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'seat_based' => 'bool',
        'is_featured' => 'bool',
        'max_seats' => 'int',
        'features' => 'array',
        'entitlements' => 'array',
        'synced_at' => 'datetime',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }
}
