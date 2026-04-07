<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SurveyQuestionType: string implements HasLabel
{
    case ShortText = 'short_text';
    case LongText = 'long_text';
    case SingleChoice = 'single_choice';
    case MultipleChoice = 'multiple_choice';
    case Rating = 'rating';
    case Nps = 'nps';
    case YesNo = 'yes_no';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::ShortText => 'Short text',
            self::LongText => 'Long text',
            self::SingleChoice => 'Single choice',
            self::MultipleChoice => 'Multiple choice',
            self::Rating => 'Rating',
            self::Nps => 'NPS',
            self::YesNo => 'Yes / No',
        };
    }
}
