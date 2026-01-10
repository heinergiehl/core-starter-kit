<?php

namespace App\Notifications;

use App\Domain\Organization\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TeamInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly TeamInvitation $invitation)
    {
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $teamName = $this->invitation->team?->name ?? 'a workspace';
        $url = route('invitations.accept', $this->invitation->token);

        return (new MailMessage)
            ->subject("You're invited to join {$teamName}")
            ->greeting('You have a workspace invite')
            ->line("{$this->invitation->invitedBy?->name} invited you to join {$teamName}.")
            ->action('Accept invitation', $url)
            ->line('This invitation will expire soon. If you were not expecting this invite, you can ignore this email.');
    }
}
