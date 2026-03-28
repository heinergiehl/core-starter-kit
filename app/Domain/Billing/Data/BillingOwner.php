<?php

declare(strict_types=1);

namespace App\Domain\Billing\Data;

use App\Domain\Identity\Models\Account;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final readonly class BillingOwner
{
    public function __construct(
        public string $type,
        public int $id,
        public int $billingUserId,
        public ?User $user = null,
        public ?Account $account = null,
    ) {}

    public static function forUser(User $user): self
    {
        return new self(
            type: 'user',
            id: (int) $user->getKey(),
            billingUserId: (int) $user->getKey(),
            user: $user,
        );
    }

    public static function forAccount(Account $account, int $billingUserId, ?User $user = null): self
    {
        return new self(
            type: 'account',
            id: (int) $account->getKey(),
            billingUserId: $billingUserId,
            user: $user,
            account: $account,
        );
    }

    public static function forAccountId(int $accountId, int $billingUserId, ?User $user = null): self
    {
        return new self(
            type: 'account',
            id: $accountId,
            billingUserId: $billingUserId,
            user: $user,
        );
    }

    public function isUser(): bool
    {
        return $this->type === 'user';
    }

    public function isAccount(): bool
    {
        return $this->type === 'account';
    }

    public function billingUserId(): int
    {
        return $this->billingUserId;
    }

    public function accountId(): ?int
    {
        return $this->isAccount() ? $this->id : null;
    }

    public function applyToQuery(Builder $query, string $accountColumn = 'account_id', string $userColumn = 'user_id'): Builder
    {
        if ($this->isAccount()) {
            return $query->where($accountColumn, $this->id);
        }

        return $query->where($userColumn, $this->billingUserId);
    }

    public function cacheKeySuffix(): string
    {
        return "{$this->type}:{$this->id}";
    }
}
