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
        'allow_custom_amount',
        'is_metered',
        'usage_meter_name',
        'usage_meter_key',
        'usage_unit_label',
        'usage_included_units',
        'usage_package_size',
        'usage_overage_amount',
        'usage_rounding_mode',
        'usage_limit_behavior',
        'custom_amount_minimum',
        'custom_amount_maximum',
        'custom_amount_default',
        'suggested_amounts',
        'type',
        'has_trial',
        'trial_interval',
        'trial_interval_count',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'bool',
        'amount' => 'int',
        'allow_custom_amount' => 'bool',
        'is_metered' => 'bool',
        'usage_included_units' => 'int',
        'usage_package_size' => 'int',
        'usage_overage_amount' => 'int',
        'usage_limit_behavior' => \App\Enums\UsageLimitBehavior::class,
        'custom_amount_minimum' => 'int',
        'custom_amount_maximum' => 'int',
        'custom_amount_default' => 'int',
        'suggested_amounts' => 'array',
        'type' => \App\Enums\PriceType::class,
        'interval_count' => 'int',
        'has_trial' => 'bool',
        'trial_interval_count' => 'int',
    ];

    public function mappings(): HasMany
    {
        return $this->hasMany(PriceProviderMapping::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
