<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Price extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Domain\Billing\PriceFactory::new();
    }

    protected $fillable = [
        'product_id',
        'key',
        // 'provider', // Removed: use providerMappings
        // 'provider_id', // Removed: use providerMappings
        'label',
        'interval',
        'interval_count',
        'currency',
        'amount',
        'type',
        'has_trial',
        'trial_interval',
        'trial_interval_count',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'amount' => 'int',
        'interval_count' => 'int',
        'has_trial' => 'bool',
        'trial_interval_count' => 'int',
    ];

    public function mappings(): HasMany
    {
        return $this->hasMany(PriceProviderMapping::class);
    }
    
    /**
     * @deprecated Use mappings instead
     */
    public function providerMappings(): HasMany
    {
        return $this->hasMany(PriceProviderMapping::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

