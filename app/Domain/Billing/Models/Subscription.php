<?php

namespace App\Domain\Billing\Models;

use App\Domain\Organization\Models\Team;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Stancl\Tenancy\Database\Concerns\BelongsToTenant;

class Subscription extends Model
{
    use HasFactory, BelongsToTenant;



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
        'canceled_at' => 'datetime',
        'welcome_email_sent_at' => 'datetime',
        'trial_started_email_sent_at' => 'datetime',
        'cancellation_email_sent_at' => 'datetime',
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
