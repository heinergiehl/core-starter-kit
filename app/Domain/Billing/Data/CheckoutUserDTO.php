<?php

namespace App\Domain\Billing\Data;

use App\Models\User;

class CheckoutUserDTO
{
    public function __construct(
        public User $user,
        public bool $created
    ) {}
}
