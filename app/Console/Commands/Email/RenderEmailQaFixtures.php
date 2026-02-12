<?php

namespace App\Console\Commands\Email;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class RenderEmailQaFixtures extends Command
{
    protected $signature = 'email:qa:render
        {--output=storage/app/email-qa : Directory where rendered fixtures are written}';

    protected $description = 'Render HTML and plain-text fixtures for all transactional emails to support client QA.';

    public function handle(): int
    {
        $outputPath = $this->resolveOutputPath((string) $this->option('output'));

        File::ensureDirectoryExists($outputPath);

        $fixtures = $this->fixtures();

        foreach ($fixtures as $name => $fixture) {
            $html = view($fixture['html'], $fixture['data'])->render();
            $text = view($fixture['text'], $fixture['data'])->render();

            File::put($outputPath.DIRECTORY_SEPARATOR.$name.'.html', $html);
            File::put($outputPath.DIRECTORY_SEPARATOR.$name.'.txt', trim($text).PHP_EOL);
        }

        $this->info('Rendered '.count($fixtures)." email fixture pairs to {$outputPath}");
        $this->line('Use these files in your email client QA tooling (Litmus/Email on Acid/Mailpit).');

        return self::SUCCESS;
    }

    private function resolveOutputPath(string $path): string
    {
        $trimmed = trim($path);

        if ($trimmed === '') {
            return storage_path('app/email-qa');
        }

        if ($this->isAbsolutePath($trimmed)) {
            return $trimmed;
        }

        return base_path($trimmed);
    }

    private function isAbsolutePath(string $path): bool
    {
        return str_starts_with($path, DIRECTORY_SEPARATOR)
            || preg_match('/^[A-Za-z]:[\\\\\\/]/', $path) === 1;
    }

    /**
     * @return array<string, array{html:string,text:string,data:array<string,mixed>}>
     */
    private function fixtures(): array
    {
        $user = (object) [
            'name' => 'Sample Customer',
            'email' => 'customer@example.com',
        ];

        return [
            'auth-verify-email' => [
                'html' => 'emails.auth.verify-email',
                'text' => 'emails.text.auth.verify-email',
                'data' => [
                    'url' => config('app.url', 'http://localhost').'/verify-email?sample=1',
                ],
            ],
            'auth-reset-password' => [
                'html' => 'emails.auth.reset-password',
                'text' => 'emails.text.auth.reset-password',
                'data' => [
                    'url' => config('app.url', 'http://localhost').'/reset-password/sample-token',
                    'count' => 60,
                ],
            ],
            'welcome' => [
                'html' => 'emails.welcome',
                'text' => 'emails.text.welcome',
                'data' => [
                    'user' => $user,
                ],
            ],
            'test' => [
                'html' => 'emails.test',
                'text' => 'emails.text.test',
                'data' => [
                    'messageText' => 'Fixture render for QA validation.',
                ],
            ],
            'billing-payment-failed' => [
                'html' => 'emails.billing.payment-failed',
                'text' => 'emails.text.billing.payment-failed',
                'data' => [
                    'user' => $user,
                    'planName' => 'Pro Plan',
                    'amount' => 5900,
                    'currency' => 'USD',
                    'failureReason' => 'Card expired',
                ],
            ],
            'payment-successful' => [
                'html' => 'emails.payment.successful',
                'text' => 'emails.text.payment.successful',
                'data' => [
                    'user' => $user,
                    'planName' => 'Pro Plan',
                    'amount' => 5900,
                    'currency' => 'USD',
                    'receiptUrl' => config('app.url', 'http://localhost').'/receipts/sample',
                ],
            ],
            'subscription-started' => [
                'html' => 'emails.subscription.started',
                'text' => 'emails.text.subscription.started',
                'data' => [
                    'user' => $user,
                    'planName' => 'Growth Plan',
                    'amount' => 9900,
                    'currency' => 'USD',
                    'features' => ['Unlimited projects', 'Priority support', 'Advanced analytics'],
                ],
            ],
            'subscription-trial-started' => [
                'html' => 'emails.subscription.trial-started',
                'text' => 'emails.text.subscription.trial-started',
                'data' => [
                    'user' => $user,
                    'planName' => 'Growth Plan',
                    'trialEndsAt' => now()->addDays(14)->toDateString(),
                    'features' => ['Unlimited projects', 'Priority support', 'Advanced analytics'],
                ],
            ],
            'subscription-plan-changed' => [
                'html' => 'emails.subscription.plan-changed',
                'text' => 'emails.text.subscription.plan-changed',
                'data' => [
                    'user' => $user,
                    'previousPlanName' => 'Starter',
                    'newPlanName' => 'Pro',
                    'effectiveDate' => now()->addDays(1)->toDateString(),
                ],
            ],
            'subscription-resumed' => [
                'html' => 'emails.subscription.resumed',
                'text' => 'emails.text.subscription.resumed',
                'data' => [
                    'user' => $user,
                    'planName' => 'Pro',
                ],
            ],
            'subscription-cancelled' => [
                'html' => 'emails.subscription.cancelled',
                'text' => 'emails.text.subscription.cancelled',
                'data' => [
                    'user' => $user,
                    'planName' => 'Pro',
                    'accessUntil' => now()->addDays(30)->toDateString(),
                ],
            ],
        ];
    }
}
