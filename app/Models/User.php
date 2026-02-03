<?php

namespace App\Models;

use App\Domain\Billing\Models\Subscription;
use App\Domain\Feedback\Models\FeatureRequest;
use App\Domain\Feedback\Models\FeatureVote;
use App\Domain\Identity\Models\SocialAccount;
use App\Domain\Identity\Models\TwoFactorAuth;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use App\Domain\Billing\Traits\HasEntitlements;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, HasRoles, Notifiable, HasEntitlements;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'locale',
        'onboarding_completed_at',
        'email_verified_at',
    ];

    protected string $guard_name = 'web';

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'bool',
            'onboarding_completed_at' => 'datetime',
            'locale' => \App\Enums\Locale::class,
        ];
    }

    public function socialAccounts(): HasMany
    {
        return $this->hasMany(SocialAccount::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()->getQuery()
            ->where(function ($q) {
                $q->whereIn('status', ['active', 'trialing', 'past_due'])
                  ->orWhere(function ($q2) {
                      $q2->where('status', 'canceled')
                         ->where('ends_at', '>', now());
                  });
            })
            ->latest('id')
            ->first();
    }

    public function hasActiveSubscription(): bool
    {
        return $this->activeSubscription() !== null;
    }

    public function featureRequests(): HasMany
    {
        return $this->hasMany(FeatureRequest::class);
    }

    public function featureVotes(): HasMany
    {
        return $this->hasMany(FeatureVote::class);
    }

    public function twoFactorAuth(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(TwoFactorAuth::class);
    }

    public function hasTwoFactorEnabled(): bool
    {
        return $this->twoFactorAuth?->isEnabled() ?? false;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === 'admin') {
            return $this->is_admin || $this->can('access_admin_panel');
        }

        return true;
    }

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new \App\Notifications\Auth\VerifyEmailNotification);
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\Auth\ResetPasswordNotification($token));
    }
}
