<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum FeatureCategory: string implements HasLabel
{
    case Feature = 'feature';
    case Bug = 'bug';
    case Improvement = 'improvement';
    case Other = 'other';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Feature => __('Feature'),
            self::Bug => __('Bug'),
            self::Improvement => __('Improvement'),
            self::Other => __('Other'),
        };
    }
}
