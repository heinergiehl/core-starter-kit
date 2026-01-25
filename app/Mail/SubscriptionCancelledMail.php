<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email sent when a subscription is cancelled.
 */
class SubscriptionCancelledMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly object $user,
        public readonly ?string $planName = null,
        public readonly ?string $accessUntil = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your subscription has been cancelled',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription.cancelled',
            with: [
                'user' => $this->user,
                'planName' => $this->planName,
                'accessUntil' => $this->accessUntil,
            ],
        );
    }
}
