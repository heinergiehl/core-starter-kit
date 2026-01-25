<?php

namespace App\Domain\Settings\Mail;

class MailgunProvider implements MailProvider
{
    public function id(): string
    {
        return 'mailgun';
    }

    public function label(): string
    {
        return 'Mailgun';
    }

    public function apply(array $settings): void
    {
        config([
            'services.mailgun.domain' => $settings['mail.mailgun.domain'] ?? null,
            'services.mailgun.secret' => $settings['mail.mailgun.secret'] ?? null,
            'services.mailgun.endpoint' => $settings['mail.mailgun.endpoint'] ?? null,
        ]);
    }
}
