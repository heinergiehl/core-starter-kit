<?php

namespace App\Domain\Settings\Mail;

interface MailProvider
{
    public function id(): string;

    public function label(): string;

    /**
     * @param  array<string, mixed>  $settings
     */
    public function apply(array $settings): void;
}
