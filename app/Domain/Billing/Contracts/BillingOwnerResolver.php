<?php

declare(strict_types=1);

namespace App\Domain\Billing\Contracts;

use App\Domain\Billing\Data\BillingOwner;
use App\Models\User;

interface BillingOwnerResolver
{
    public function current(): ?BillingOwner;

    public function forUser(?User $user): ?BillingOwner;
}
