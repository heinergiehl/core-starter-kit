<?php

namespace App\Domain\Billing\Models;

use App\Domain\Organization\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CheckoutIntent extends Model
{
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'provider',
        'plan_key',
        'price_key',
        'quantity',
        'status',
        'currency',
        'amount',
        'amount_is_minor',
        'email',
        'user_id',
        'team_id',
        'discount_id',
        'discount_code',
        'provider_transaction_id',
        'provider_subscription_id',
        'provider_customer_id',
        'payload',
        'metadata',
        'claim_sent_at',
        'claimed_at',
    ];

    protected $casts = [
        'quantity' => 'int',
        'amount' => 'int',
        'amount_is_minor' => 'bool',
        'payload' => 'array',
        'metadata' => 'array',
        'claim_sent_at' => 'datetime',
        'claimed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (CheckoutIntent $intent): void {
            if (!$intent->getKey()) {
                $intent->setAttribute($intent->getKeyName(), (string) Str::uuid());
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
