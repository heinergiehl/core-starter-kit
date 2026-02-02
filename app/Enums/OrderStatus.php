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
    case Ready = 'ready';
    case Draft = 'draft';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Paid => __('Paid'),
            self::Completed => __('Completed'),
            self::Pending => __('Pending'),
            self::Failed => __('Failed'),
            self::Refunded => __('Refunded'),
            self::Open => __('Open'),
            self::Ready => __('Ready'),
            self::Draft => __('Draft'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Paid, self::Completed, self::Ready => 'success',
            self::Pending, self::Open, self::Draft => 'warning',
            self::Failed => 'danger',
            self::Refunded => 'gray',
        };
    }
}
