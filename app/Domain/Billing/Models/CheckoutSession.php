<?php

namespace App\Domain\Billing\Models;

use App\Domain\Organization\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CheckoutSession extends Model
{
    protected $fillable = [
        'uuid',
        'user_id',
        'team_id',
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
    ];

    protected static function booted(): void
    {
        static::creating(function (CheckoutSession $session) {
            if (!$session->uuid) {
                $session->uuid = (string) Str::uuid();
            }
            
            if (!$session->expires_at) {
                $session->expires_at = now()->addHour();
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
     * Get the team that owns the checkout session.
     */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
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
        return $this->status === 'pending' && !$this->isExpired();
    }

    /**
     * Mark the session as completed.
     */
    public function markCompleted(): void
    {
        $this->update([
            'status' => 'completed',
            'completed_at' => now(),
        ]);
    }

    /**
     * Mark the session as canceled.
     */
    public function markCanceled(): void
    {
        $this->update([
            'status' => 'canceled',
        ]);
    }

    /**
     * Scope to only valid sessions.
     */
    public function scopeValid($query)
    {
        return $query->where('status', 'pending')
            ->where('expires_at', '>', now());
    }
}
