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
        'is_featured',
        'features',
        'entitlements',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'is_featured' => 'bool',
        'features' => 'array',
        'entitlements' => 'array',
    ];

    public function providerMappings(): HasMany
    {
        return $this->hasMany(ProductProviderMapping::class);
    }

    public function prices(): HasMany
    {
        return $this->hasMany(Price::class);
    }
}
