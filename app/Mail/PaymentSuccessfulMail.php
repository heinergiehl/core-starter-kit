<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * Email sent when a one-time payment is successful.
 */
class PaymentSuccessfulMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly object $user,
        public readonly ?string $planName = null,
        public readonly ?int $amount = null,
        public readonly ?string $currency = null,
        public readonly ?string $receiptUrl = null,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Payment successful',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.payment.successful',
            text: 'emails.text.payment.successful',
            with: [
                'user' => $this->user,
                'planName' => $this->planName,
                'amount' => $this->amount,
                'currency' => $this->currency,
                'receiptUrl' => $this->receiptUrl,
            ],
        );
    }
}
