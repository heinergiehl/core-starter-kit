<?php

namespace App\Domain\Settings\Services;

use App\Domain\Settings\Mail\MailgunProvider;
use App\Domain\Settings\Mail\MailProvider;
use App\Domain\Settings\Mail\PostmarkProvider;
use App\Domain\Settings\Mail\SesProvider;

class MailSettingsService
{
    /**
     * @return array<int, MailProvider>
     */
    public function providers(): array
    {
        return [
            new MailgunProvider,
            new PostmarkProvider,
            new SesProvider,
        ];
    }

    public function applyConfig(): void
    {
        $settings = app(AppSettingsService::class);

        if (! $settings->isAvailable()) {
            return;
        }

        $providerId = $settings->get('mail.provider');

        if ($providerId) {
            config(['mail.default' => $providerId]);
        }

        $fromAddress = $settings->get('mail.from.address');
        $fromName = $settings->get('mail.from.name');

        if ($fromAddress !== null) {
            config(['mail.from.address' => $fromAddress]);
        }

        if ($fromName !== null) {
            config(['mail.from.name' => $fromName]);
        }

        $provider = $this->resolveProvider((string) $providerId);
        if ($provider) {
            $provider->apply($settings->all());
        }
    }

    public function providerOptions(): array
    {
        $options = [];
        foreach ($this->providers() as $provider) {
            $options[$provider->id()] = $provider->label();
        }

        return $options;
    }

    private function resolveProvider(string $providerId): ?MailProvider
    {
        foreach ($this->providers() as $provider) {
            if ($provider->id() === $providerId) {
                return $provider;
            }
        }

        return null;
    }
}
