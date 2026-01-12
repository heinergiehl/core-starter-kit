<?php

namespace App\Domain\Organization\Models;

use App\Domain\Billing\Models\Subscription;
use App\Domain\Organization\Enums\TeamRole;
use App\Domain\Settings\Models\BrandSetting;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Team extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Domain\Organization\TeamFactory::new();
    }

    protected $fillable = [
        'name',
        'slug',
        'owner_id',
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->using(TeamUser::class)
            ->withPivot(['role', 'joined_at'])
            ->withTimestamps();
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(TeamInvitation::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function brandSetting(): HasOne
    {
        return $this->hasOne(BrandSetting::class);
    }

    /**
     * Get the team's active subscription.
     */
    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->latest('id')
            ->first();
    }

    /**
     * Check if the team has an active subscription.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->subscriptions()
            ->whereIn('status', ['active', 'trialing'])
            ->exists();
    }

    public function isOwner(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    public function hasRole(User $user, TeamRole $role): bool
    {
        if ($this->isOwner($user)) {
            return true;
        }

        $memberRole = $this->members()
            ->where('users.id', $user->id)
            ->value('team_user.role');

        return $memberRole === $role->value;
    }
}
