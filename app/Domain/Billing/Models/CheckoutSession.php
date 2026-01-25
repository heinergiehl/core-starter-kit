<?php

namespace App\Domain\Billing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CheckoutSession extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'provider',
        'provider_session_id',
        'plan_key',
        'price_key',
        'quantity',
        'status',
        'expires_at',
        'completed_at',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'status' => \App\Enums\CheckoutStatus::class,
    ];

    protected static function booted(): void
    {
        static::creating(function (CheckoutSession $session) {
            if (! $session->uuid) {
                $session->uuid = (string) Str::uuid();
            }

            if (! $session->expires_at) {
                $session->expires_at = now()->addHour();
            }

            if (! $session->status) {
                $session->status = \App\Enums\CheckoutStatus::Pending;
            }
        });
    }

    /**
     * Get the user that owns the checkout session.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if the session has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if the session is still valid (pending and not expired).
     */
    public function isValid(): bool
    {
        return $this->status === \App\Enums\CheckoutStatus::Pending && ! $this->isExpired();
    }

    /**
     * Mark the session as completed.
     */
    public function markCompleted(): void
    {
        $this->update([
            'status' => \App\Enums\CheckoutStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark the session as canceled.
     */
    public function markCanceled(): void
    {
        $this->update([
            'status' => \App\Enums\CheckoutStatus::Canceled,
        ]);
    }

    /**
     * Scope to only valid sessions.
     */
    public function scopeValid($query)
    {
        return $query->where('status', \App\Enums\CheckoutStatus::Pending)
            ->where('expires_at', '>', now());
    }
}
