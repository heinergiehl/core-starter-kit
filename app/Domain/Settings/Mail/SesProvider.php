<?php

namespace App\Domain\Settings\Mail;

class SesProvider implements MailProvider
{
    public function id(): string
    {
        return 'ses';
    }

    public function label(): string
    {
        return 'Amazon SES';
    }

    public function apply(array $settings): void
    {
        config([
            'services.ses.key' => $settings['mail.ses.key'] ?? null,
            'services.ses.secret' => $settings['mail.ses.secret'] ?? null,
            'services.ses.region' => $settings['mail.ses.region'] ?? null,
        ]);
    }
}
