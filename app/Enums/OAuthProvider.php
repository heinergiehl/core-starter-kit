<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum OAuthProvider: string implements HasColor, HasLabel
{
    case Google = 'google';
    case GitHub = 'github';
    case Twitter = 'twitter';
    case Facebook = 'facebook';
    case LinkedIn = 'linkedin';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Google => 'Google',
            self::GitHub => 'GitHub',
            self::Twitter => 'Twitter',
            self::Facebook => 'Facebook',
            self::LinkedIn => 'LinkedIn',
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Google => 'danger',
            self::GitHub => 'gray',
            self::Twitter => 'info',
            self::Facebook => 'primary',
            self::LinkedIn => 'info',
        };
    }
}
