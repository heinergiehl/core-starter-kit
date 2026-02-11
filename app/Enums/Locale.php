<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Locale: string implements HasLabel
{
    case English = 'en';
    case German = 'de';
    case Spanish = 'es';
    case French = 'fr';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::English => 'English',
            self::German => 'Deutsch',
            self::Spanish => 'Espanol',
            self::French => 'Francais',
        };
    }
}
