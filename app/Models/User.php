<?php

namespace App\Models;

use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Traits\HasEntitlements;
use App\Domain\Feedback\Models\FeatureRequest;
use App\Domain\Feedback\Models\FeatureVote;
use App\Domain\Identity\Contracts\CurrentAccountResolver as CurrentAccountResolverContract;
use App\Domain\Identity\Models\Account;
use App\Domain\Identity\Models\AccountMembership;
use App\Domain\Identity\Models\SocialAccount;
use App\Domain\Identity\Models\TwoFactorAuth;
use App\Domain\RepoAccess\Models\RepoAccessGrant;
use App\Enums\PermissionName;
use App\Support\Authorization\PermissionGuardrails;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasEntitlements, HasFactory, HasRoles, Notifiable;

    protected static function booted(): void
    {
        static::created(function (self $user): void {
            $user->ensurePersonalAccount();
        });

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
        'public_author_name',
        'public_author_title',
        'public_author_avatar_path',
        'public_author_bio',
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

    public function personalAccount(): HasOne
    {
        return $this->hasOne(Account::class, 'personal_for_user_id');
    }

    public function accountMemberships(): HasMany
    {
        return $this->hasMany(AccountMembership::class);
    }

    public function accounts(): BelongsToMany
    {
        return $this->belongsToMany(Account::class, 'account_memberships')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function currentAccount(): ?Account
    {
        if (! $this->exists || ! Schema::hasTable('accounts')) {
            return null;
        }

        if (app()->bound(CurrentAccountResolverContract::class)) {
            $account = app(CurrentAccountResolverContract::class)->forUser($this);

            if ($account) {
                return $account;
            }
        }

        return $this->personalAccount()->first() ?? $this->ensurePersonalAccount();
    }

    public function currentAccountId(): ?int
    {
        if (! $this->exists) {
            return null;
        }

        if (app()->bound(CurrentAccountResolverContract::class)) {
            return app(CurrentAccountResolverContract::class)->idForUser($this);
        }

        return $this->personalAccount()->value('id') ?? $this->ensurePersonalAccount()?->id;
    }

    public function ensurePersonalAccount(): ?Account
    {
        if (! $this->exists || ! Schema::hasTable('accounts') || ! Schema::hasTable('account_memberships')) {
            return null;
        }

        $account = $this->personalAccount()->firstOrCreate(
            ['personal_for_user_id' => $this->id],
            ['name' => $this->personalAccountName()]
        );

        AccountMembership::query()->firstOrCreate(
            [
                'account_id' => $account->id,
                'user_id' => $this->id,
            ],
            [
                'role' => 'owner',
            ]
        );

        return $account;
    }

    public function activeSubscription(): ?Subscription
    {
        $accountId = $this->currentAccountId();

        if (! $accountId) {
            return $this->subscriptions()
                ->isActive()
                ->latest('id')
                ->first();
        }

        return Subscription::query()
            ->where('account_id', $accountId)
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

    public function publicAuthorName(): string
    {
        return trim((string) ($this->public_author_name ?: $this->name));
    }

    public function publicAuthorTitle(): ?string
    {
        $title = trim((string) ($this->public_author_title ?? ''));

        return $title !== '' ? $title : null;
    }

    public function publicAuthorBio(): ?string
    {
        $bio = trim((string) ($this->public_author_bio ?? ''));

        return $bio !== '' ? $bio : null;
    }

    public function publicAuthorAvatarUrl(): ?string
    {
        $avatarPath = trim((string) ($this->public_author_avatar_path ?? ''));

        return $avatarPath !== '' ? Storage::disk('public')->url($avatarPath) : null;
    }

    public function publicAuthorInitial(): string
    {
        return Str::upper(Str::substr($this->publicAuthorName(), 0, 1));
    }

    private function personalAccountName(): string
    {
        $name = trim((string) $this->name);

        return $name !== '' ? "{$name} Personal" : 'Personal Account';
    }
}
