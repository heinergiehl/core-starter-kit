<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email sent when a subscription is successfully activated.
 */
class SubscriptionStartedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly object $user,
        public readonly ?string $planName = null,
        public readonly ?int $amount = null,
        public readonly ?string $currency = null,
        public readonly array $features = [],
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your subscription is now active!',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription.started',
            with: [
                'user' => $this->user,
                'planName' => $this->planName,
                'amount' => $this->amount,
                'currency' => $this->currency,
                'features' => $this->features,
            ],
        );
    }
}
