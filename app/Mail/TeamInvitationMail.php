<?php

namespace App\Mail;

use App\Domain\Organization\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Styled team invitation email.
 */
class TeamInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly TeamInvitation $invitation,
    ) {
    }

    public function envelope(): Envelope
    {
        $teamName = $this->invitation->team?->name ?? 'a team';
        
        return new Envelope(
            subject: "You're invited to join {$teamName}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.team.invitation',
            with: [
                'teamName' => $this->invitation->team?->name ?? 'Team',
                'inviterName' => $this->invitation->invitedBy?->name,
                'inviteUrl' => route('invitations.accept', $this->invitation->token),
                'role' => $this->invitation->role,
                'expiresIn' => config('saas.invites.expires_days', 7) . ' days',
            ],
        );
    }
}
