<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email sent when a subscription plan is updated.
 */
class SubscriptionPlanChangedMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly object $user,
        public readonly ?string $previousPlanName = null,
        public readonly ?string $newPlanName = null,
        public readonly ?string $effectiveDate = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Your plan has been updated',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.subscription.plan-changed',
            text: 'emails.text.subscription.plan-changed',
            with: [
                'user' => $this->user,
                'previousPlanName' => $this->previousPlanName,
                'newPlanName' => $this->newPlanName,
                'effectiveDate' => $this->effectiveDate,
            ],
        );
    }
}
