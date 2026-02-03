<?php

namespace App\Domain\Billing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\SubscriptionStatus;
use App\Enums\BillingProvider;

class Subscription extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Domain\Billing\SubscriptionFactory::new();
    }

    protected static function booted(): void
    {
        static::created(function (Subscription $subscription) {
            if ($subscription->user_id) {
                \Illuminate\Support\Facades\Cache::forget("entitlements:user:{$subscription->user_id}");
            }
        });

        static::updated(function (Subscription $subscription) {
            if ($subscription->user_id) {
                \Illuminate\Support\Facades\Cache::forget("entitlements:user:{$subscription->user_id}");
            }
        });

        static::deleted(function (Subscription $subscription) {
             if ($subscription->user_id) {
                \Illuminate\Support\Facades\Cache::forget("entitlements:user:{$subscription->user_id}");
            }
        });
    }

    public function clearUserCache(): void
    {
        try {
            if ($this->user_id) {
                \Illuminate\Support\Facades\Cache::forget("entitlements:user:{$this->user_id}");
            }
        } catch (\Throwable $e) {
            dd("clearUserCache failed: " . $e->getMessage(), $e->getTraceAsString());
        }
    }

    protected $fillable = [
        'user_id',
        'provider',
        'provider_id',
        'plan_key',
        'status',
        'quantity',
        'trial_ends_at',
        'renews_at',
        'ends_at',
        'canceled_at',
        'welcome_email_sent_at',
        'trial_started_email_sent_at',
        'cancellation_email_sent_at',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'int',
        'trial_ends_at' => 'datetime',
        'renews_at' => 'datetime',
        'ends_at' => 'datetime',
        'status' => SubscriptionStatus::class,
        'provider' => BillingProvider::class,
        'canceled_at' => 'datetime',
        'welcome_email_sent_at' => 'datetime',
        'trial_started_email_sent_at' => 'datetime',
        'cancellation_email_sent_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Determine if the subscription is currently on trial.
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }

    public function scopeIsActive($query)
    {
        return $query->where(function ($q) {
            $q->whereIn('status', ['active', 'trialing', 'past_due'])
              ->orWhere(function ($q2) {
                  $q2->where('status', 'canceled')
                     ->where('ends_at', '>', now());
              });
        });
    }

    public function scopePendingCancellation($query)
    {
        return $query->whereNotNull('canceled_at')
            ->where('ends_at', '>', now());
    }
}
