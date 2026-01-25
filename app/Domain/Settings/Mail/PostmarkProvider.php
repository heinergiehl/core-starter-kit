<?php

namespace App\Domain\Settings\Mail;

class PostmarkProvider implements MailProvider
{
    public function id(): string
    {
        return 'postmark';
    }

    public function label(): string
    {
        return 'Postmark';
    }

    public function apply(array $settings): void
    {
        config([
            'services.postmark.token' => $settings['mail.postmark.token'] ?? null,
        ]);
    }
}
