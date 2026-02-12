<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class TestEmail extends Mailable
{
    public function __construct(public string $messageText) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Test Email');
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.test',
            text: 'emails.text.test',
            with: [
                'messageText' => $this->messageText,
            ]
        );
    }
}
