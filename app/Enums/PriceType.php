<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum PriceType: string implements HasLabel
{
    case Recurring = 'recurring';
    case OneTime = 'one_time';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Recurring => __('Recurring'),
            self::OneTime => __('One Time'),
        };
    }
}
