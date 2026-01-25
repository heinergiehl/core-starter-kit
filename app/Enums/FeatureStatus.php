<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum FeatureStatus: string implements HasColor, HasLabel
{
    case Pending = 'pending';
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Pending => __('Pending'),
            self::Planned => __('Planned'),
            self::InProgress => __('In Progress'),
            self::Completed => __('Completed'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Planned => 'info',
            self::InProgress => 'warning',
            self::Completed => 'success',
        };
    }
}
