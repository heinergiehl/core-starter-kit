<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Domain\Billing\ProductFactory::new();
    }

    protected $fillable = [
        'key',
        'name',
        'description',
        'summary',
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

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }
}
