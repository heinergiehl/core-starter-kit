<?php

namespace App\Domain\Billing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Enums\SubscriptionStatus;
use App\Enums\BillingProvider;

use App\Domain\Billing\Services\EntitlementService;

class Subscription extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Domain\Billing\SubscriptionFactory::new();
    }

    protected static function booted(): void
    {
        static::saved(function (Subscription $subscription) {
            self::forgetUserEntitlements($subscription->user_id, $subscription->id);
        });

        static::deleted(function (Subscription $subscription) {
            self::forgetUserEntitlements($subscription->user_id, $subscription->id);
        });
    }

    private static function forgetUserEntitlements(?int $userId, ?int $subscriptionId = null): void
    {
        try {
            if ($userId) {
                \Illuminate\Support\Facades\Cache::forget(EntitlementService::CACHE_KEY_PREFIX . $userId);
            }
        } catch (\Throwable $e) {
            report($e);
            \Illuminate\Support\Facades\Log::warning('clearUserCache failed', [
                'subscription_id' => $subscriptionId,
                'user_id' => $userId,
            ]);
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
        $activeStatuses = [
            SubscriptionStatus::Active->value,
            SubscriptionStatus::Trialing->value,
            SubscriptionStatus::PastDue->value,
        ];

        return $query->where(function ($q) use ($activeStatuses) {
            $q->whereIn('status', $activeStatuses)
                ->orWhere(function ($q2) {
                    $q2->where('status', SubscriptionStatus::Canceled->value)
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
