<?php

namespace App\Models;

use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Traits\HasEntitlements;
use App\Domain\Feedback\Models\FeatureRequest;
use App\Domain\Feedback\Models\FeatureVote;
use App\Domain\Identity\Models\SocialAccount;
use App\Domain\Identity\Models\TwoFactorAuth;
use App\Domain\RepoAccess\Models\RepoAccessGrant;
use App\Enums\PermissionName;
use App\Support\Authorization\PermissionGuardrails;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasEntitlements, HasFactory, HasRoles, Notifiable;

    protected static function booted(): void
    {
        static::saving(function (self $user): void {
            if (! $user->exists || ! $user->isDirty('is_admin')) {
                return;
            }

            $wasAdmin = (bool) $user->getOriginal('is_admin');
            $willBeAdmin = (bool) $user->is_admin;

            if (! $wasAdmin || $willBeAdmin) {
                return;
            }

            $hasOtherAdmins = self::query()
                ->where('is_admin', true)
                ->whereKeyNot($user->getKey())
                ->exists();

            if ($hasOtherAdmins) {
                return;
            }

            throw ValidationException::withMessages([
                'is_admin' => PermissionGuardrails::lastAdminDemotionMessage(),
            ]);
        });

        static::deleting(function (self $user): void {
            if (! PermissionGuardrails::isLastAdminUser($user)) {
                return;
            }

            throw ValidationException::withMessages([
                'user' => PermissionGuardrails::lastAdminDeleteMessage(),
            ]);
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'locale',
        'onboarding_completed_at',
    ];

    protected string $guard_name = PermissionGuardrails::DEFAULT_GUARD;

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

    public function repoAccessGrants(): HasMany
    {
        return $this->hasMany(RepoAccessGrant::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function activeSubscription(): ?Subscription
    {
        return $this->subscriptions()
            ->isActive()
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

    public function canAccessAdminPanel(): bool
    {
        return $this->is_admin || $this->can(PermissionName::AccessAdminPanel->value);
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if ($panel->getId() === PermissionGuardrails::ADMIN_PANEL_ID) {
            return $this->canAccessAdminPanel();
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
