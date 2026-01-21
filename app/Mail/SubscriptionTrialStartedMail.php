<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email sent when a subscription trial starts.
 */
class SubscriptionTrialStartedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly object $user,
        public readonly ?string $planName = null,
        public readonly ?string $trialEndsAt = null,
        public readonly array $features = [],
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your trial has started',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription.trial-started',
            with: [
                'user' => $this->user,
                'planName' => $this->planName,
                'trialEndsAt' => $this->trialEndsAt,
                'features' => $this->features,
            ],
        );
    }
}
