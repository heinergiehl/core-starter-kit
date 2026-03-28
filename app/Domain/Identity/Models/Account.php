<?php

namespace App\Domain\Identity\Models;

use App\Domain\Billing\Models\BillingCustomer;
use App\Domain\Billing\Models\CheckoutSession;
use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Schema;

class Account extends Model
{
    use HasFactory;

    protected static function newFactory()
    {
        return \Database\Factories\Domain\Identity\Models\AccountFactory::new();
    }

    protected $fillable = [
        'name',
        'personal_for_user_id',
    ];

    public function personalOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'personal_for_user_id');
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(AccountMembership::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'account_memberships')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function billingCustomers(): HasMany
    {
        return $this->hasMany(BillingCustomer::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function checkoutSessions(): HasMany
    {
        return $this->hasMany(CheckoutSession::class);
    }

    public static function resolvePersonalAccountIdForUserId(?int $userId): ?int
    {
        if (! $userId || ! Schema::hasTable('accounts')) {
            return null;
        }

        $accountId = static::query()
            ->where('personal_for_user_id', $userId)
            ->value('id');

        return $accountId ? (int) $accountId : null;
    }

    public function resolveBillingUserId(): ?int
    {
        if ($this->personal_for_user_id) {
            return (int) $this->personal_for_user_id;
        }

        $ownerMembership = $this->memberships()
            ->where('role', 'owner')
            ->oldest('id')
            ->first();

        if ($ownerMembership) {
            return (int) $ownerMembership->user_id;
        }

        $member = $this->memberships()
            ->oldest('id')
            ->first();

        return $member ? (int) $member->user_id : null;
    }
}
