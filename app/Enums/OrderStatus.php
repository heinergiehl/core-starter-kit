<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OrderStatus: string implements HasColor, HasLabel
{
    case Paid = 'paid';
    case Completed = 'completed'; // Often synonymous with Paid in some systems
    case Pending = 'pending';
    case Failed = 'failed';
    case Refunded = 'refunded';
    case Open = 'open';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Paid => __('Paid'),
            self::Completed => __('Completed'),
            self::Pending => __('Pending'),
            self::Failed => __('Failed'),
            self::Refunded => __('Refunded'),
            self::Open => __('Open'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Paid, self::Completed => 'success',
            self::Pending, self::Open => 'warning',
            self::Failed => 'danger',
            self::Refunded => 'gray',
        };
    }
}
