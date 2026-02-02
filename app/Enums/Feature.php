<?php

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum Feature: string implements HasLabel
{
    case AiWriter = 'ai_writer';
    case TeamMembers = 'team_members';
    case ServerLimit = 'server_limit';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::AiWriter => __('AI Writer'),
            self::TeamMembers => __('Team Members'),
            self::ServerLimit => __('Server Limit'),
        };
    }
}
