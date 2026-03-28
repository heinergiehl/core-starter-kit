<?php

namespace App\Domain\Billing\Models;

use App\Domain\Identity\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Order extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (Order $order): void {
            if ($order->account_id || ! $order->user_id) {
                return;
            }

            $order->account_id = Account::resolvePersonalAccountIdForUserId((int) $order->user_id);
        });
    }

    protected $fillable = [
        'user_id',
        'account_id',
        'provider',
        'provider_id',
        'plan_key',
        'status',
        'amount',
        'currency',
        'paid_at',
        'refunded_at',
        'metadata',
        'payment_success_email_sent_at',
    ];

    protected $casts = [
        'amount' => 'int',
        'paid_at' => 'datetime',
        'status' => \App\Enums\OrderStatus::class,
        'provider' => \App\Enums\BillingProvider::class,
        'refunded_at' => 'datetime',
        'metadata' => 'array',
        'payment_success_email_sent_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    /**
     * Get the product associated with this order via the plan_key.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'plan_key', 'key');
    }
}
