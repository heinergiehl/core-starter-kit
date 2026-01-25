<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum SubscriptionStatus: string implements HasColor, HasLabel
{
    case Active = 'active';
    case Trialing = 'trialing';
    case PastDue = 'past_due';
    case Canceled = 'canceled';
    case Unpaid = 'unpaid';
    case Incomplete = 'incomplete';
    case IncompleteExpired = 'incomplete_expired';
    case Paused = 'paused';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Active => __('Active'),
            self::Trialing => __('Trialing'),
            self::PastDue => __('Past Due'),
            self::Canceled => __('Canceled'),
            self::Unpaid => __('Unpaid'),
            self::Incomplete => __('Incomplete'),
            self::IncompleteExpired => __('Incomplete Expired'),
            self::Paused => __('Paused'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Active, self::Trialing => 'success',
            self::PastDue, self::Unpaid, self::Incomplete => 'warning',
            self::Canceled, self::IncompleteExpired => 'danger',
            self::Paused => 'gray',
        };
    }
}
