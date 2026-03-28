<?php

namespace App\Domain\Identity\Contracts;

use App\Domain\Identity\Models\Account;
use App\Models\User;

interface CurrentAccountResolver
{
    public function current(): ?Account;

    public function forUser(?User $user): ?Account;

    public function idForUser(?User $user): ?int;

    public function setForUser(User $user, Account $account): void;

    public function forget(): void;
}
