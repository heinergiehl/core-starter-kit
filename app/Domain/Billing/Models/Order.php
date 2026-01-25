<?php

namespace App\Domain\Billing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
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
        'status' => \App\Enums\OrderStatus::class,
        'provider' => \App\Enums\BillingProvider::class,
        'refunded_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the product associated with this order via the plan_key.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'plan_key', 'key');
    }
}
