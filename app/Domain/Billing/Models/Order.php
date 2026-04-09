<?php

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Services\EntitlementService;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class Order extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saved(function (Order $order): void {
            self::forgetUserEntitlements($order->user_id, $order->id);
        });

        static::deleted(function (Order $order): void {
            self::forgetUserEntitlements($order->user_id, $order->id);
        });
    }

    protected static function newFactory()
    {
        return \Database\Factories\Domain\Billing\OrderFactory::new();
    }

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

    /**
     * Get the product associated with this order via the plan_key.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'plan_key', 'key');
    }

    private static function forgetUserEntitlements(?int $userId, ?int $orderId = null): void
    {
        try {
            if ($userId) {
                Cache::forget(EntitlementService::CACHE_KEY_PREFIX.$userId);
            }
        } catch (\Throwable $exception) {
            report($exception);
            Log::warning('clearUserCache failed', [
                'order_id' => $orderId,
                'user_id' => $userId,
            ]);
        }
    }
}
