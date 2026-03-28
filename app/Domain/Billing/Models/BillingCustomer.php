<?php

namespace App\Domain\Billing\Models;

use App\Domain\Identity\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingCustomer extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (BillingCustomer $billingCustomer): void {
            if ($billingCustomer->account_id || ! $billingCustomer->user_id) {
                return;
            }

            $billingCustomer->account_id = Account::resolvePersonalAccountIdForUserId((int) $billingCustomer->user_id);
        });
    }

    protected $guarded = [];

    protected $fillable = [
        'user_id',
        'account_id',
        'provider',
        'provider_id',
        'email',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
