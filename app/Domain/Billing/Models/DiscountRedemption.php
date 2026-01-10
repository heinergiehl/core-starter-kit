<?php

namespace App\Domain\Billing\Models;

use App\Domain\Organization\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DiscountRedemption extends Model
{
    use HasFactory;

    protected $fillable = [
        'discount_id',
        'team_id',
        'user_id',
        'provider',
        'provider_id',
        'plan_key',
        'price_key',
        'redeemed_at',
        'metadata',
    ];

    protected $casts = [
        'redeemed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function discount(): BelongsTo
    {
        return $this->belongsTo(Discount::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
