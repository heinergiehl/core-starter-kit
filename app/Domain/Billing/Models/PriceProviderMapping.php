<?php

namespace App\Domain\Billing\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PriceProviderMapping extends Model
{
    protected $fillable = [
        'price_id',
        'provider',
        'provider_id',
    ];

    public function price(): BelongsTo
    {
        return $this->belongsTo(Price::class);
    }
}
