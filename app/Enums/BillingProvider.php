<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum BillingProvider: string implements HasLabel
{
    case Stripe = 'stripe';
    case Paddle = 'paddle';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Stripe => 'Stripe',
            self::Paddle => 'Paddle',
        };
    }
}
