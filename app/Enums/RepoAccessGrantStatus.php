<?php

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum RepoAccessGrantStatus: string implements HasColor, HasLabel
{
    case Queued = 'queued';
    case Processing = 'processing';
    case AwaitingGitHubLink = 'awaiting_github_link';
    case Invited = 'invited';
    case Granted = 'granted';
    case Failed = 'failed';

    public function getLabel(): ?string
    {
        return match ($this) {
            self::Queued => __('Queued'),
            self::Processing => __('Processing'),
            self::AwaitingGitHubLink => __('Awaiting GitHub Link'),
            self::Invited => __('Invitation Sent'),
            self::Granted => __('Access Granted'),
            self::Failed => __('Failed'),
        };
    }

    public function getColor(): string|array|null
    {
        return match ($this) {
            self::Queued, self::Processing => 'warning',
            self::AwaitingGitHubLink => 'gray',
            self::Invited, self::Granted => 'success',
            self::Failed => 'danger',
        };
    }
}
