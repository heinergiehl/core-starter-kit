<?php

namespace App\Notifications;

use App\Domain\Organization\Models\TeamInvitation;
use App\Mail\TeamInvitationMail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Team invitation notification with styled email template.
 */
class TeamInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly TeamInvitation $invitation,
    ) {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): TeamInvitationMail
    {
        return new TeamInvitationMail($this->invitation);
    }
}
