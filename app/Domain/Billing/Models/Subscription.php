<?php

namespace App\Domain\Billing\Models;

use App\Domain\Billing\Data\BillingOwner;
use App\Domain\Billing\Services\EntitlementService;
use App\Domain\Identity\Models\Account;
use App\Enums\BillingProvider;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Domain\Billing\SubscriptionFactory::new();
    }

    protected static function booted(): void
    {
        static::creating(function (Subscription $subscription): void {
            if ($subscription->account_id || ! $subscription->user_id) {
                return;
            }

            $subscription->account_id = Account::resolvePersonalAccountIdForUserId((int) $subscription->user_id);
        });

        static::saved(function (Subscription $subscription) {
            self::forgetEntitlements($subscription->account_id, $subscription->user_id, $subscription->id);
        });

        static::deleted(function (Subscription $subscription) {
            self::forgetEntitlements($subscription->account_id, $subscription->user_id, $subscription->id);
        });
    }

    private static function forgetEntitlements(?int $accountId, ?int $userId, ?int $subscriptionId = null): void
    {
        try {
            if ($accountId && $userId) {
                \Illuminate\Support\Facades\Cache::forget(
                    EntitlementService::cacheKeyForOwner(BillingOwner::forAccountId($accountId, $userId))
                );
            } elseif ($userId) {
                \Illuminate\Support\Facades\Cache::forget(EntitlementService::cacheKeyForUserId($userId));
            }
        } catch (\Throwable $e) {
            report($e);
            \Illuminate\Support\Facades\Log::warning('clearUserCache failed', [
                'subscription_id' => $subscriptionId,
                'user_id' => $userId,
                'account_id' => $accountId,
            ]);
        }
    }

    protected $fillable = [
        'user_id',
        'account_id',
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

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
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
