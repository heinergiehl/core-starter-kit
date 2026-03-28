<?php

namespace App\Domain\Identity\Services;

use App\Domain\Identity\Contracts\CurrentAccountResolver as CurrentAccountResolverContract;
use App\Domain\Identity\Models\Account;
use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;

class SessionCurrentAccountResolver implements CurrentAccountResolverContract
{
    public const SESSION_KEY = 'identity.current_account_id';

    public function current(): ?Account
    {
        $user = app()->bound('request') ? request()->user() : null;

        return $user instanceof User ? $this->forUser($user) : null;
    }

    public function forUser(?User $user): ?Account
    {
        if (! $user || ! $user->exists || ! Schema::hasTable('accounts')) {
            return null;
        }

        $sessionAccountId = $this->sessionAccountId();
        if ($sessionAccountId) {
            $account = $user->accounts()
                ->whereKey($sessionAccountId)
                ->first();

            if ($account) {
                return $account;
            }
        }

        $account = $user->personalAccount()->first() ?? $user->ensurePersonalAccount();

        if ($account) {
            $this->storeSessionAccountId((int) $account->getKey());
        }

        return $account;
    }

    public function idForUser(?User $user): ?int
    {
        return $this->forUser($user)?->id;
    }

    public function setForUser(User $user, Account $account): void
    {
        if (! $user->accounts()->whereKey($account->getKey())->exists()) {
            throw new InvalidArgumentException('Cannot set the current account to one the user does not belong to.');
        }

        $this->storeSessionAccountId((int) $account->getKey());
    }

    public function forget(): void
    {
        $session = $this->sessionStore();
        if (! $session) {
            return;
        }

        $session->forget(self::SESSION_KEY);
    }

    private function sessionAccountId(): ?int
    {
        $session = $this->sessionStore();
        if (! $session) {
            return null;
        }

        $value = $session->get(self::SESSION_KEY);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    private function storeSessionAccountId(int $accountId): void
    {
        $session = $this->sessionStore();
        if (! $session) {
            return;
        }

        $session->put(self::SESSION_KEY, $accountId);
    }

    private function sessionStore(): ?Session
    {
        if (! app()->bound('session.store')) {
            return null;
        }

        return app('session.store');
    }
}
