<?php

namespace App\Domain\Billing\Models;

use App\Domain\Organization\Models\Team;
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

    protected $fillable = [
        'team_id',
        'provider',
        'provider_id',
        'plan_key',
        'status',
        'quantity',
        'trial_ends_at',
        'renews_at',
        'ends_at',
        'canceled_at',
        'metadata',
    ];

    protected $casts = [
        'quantity' => 'int',
        'trial_ends_at' => 'datetime',
        'renews_at' => 'datetime',
        'ends_at' => 'datetime',
        'canceled_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /**
     * Determine if the subscription is currently on trial.
     */
    public function onTrial(): bool
    {
        return $this->trial_ends_at && $this->trial_ends_at->isFuture();
    }
}
