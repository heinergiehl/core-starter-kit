<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Interactive setup wizard for new SaaS Kit installations.
 *
 * Detects if already configured and offers a menu of common tasks.
 * Fresh installs get the full guided setup flow.
 *
 * @example php artisan setup:wizard
 * @example php artisan setup:wizard --force
 */
class SetupWizard extends Command
{
    private const CATALOG_SYNC_COMMAND = 'billing:publish-catalog';

    protected $signature = 'setup:wizard
        {--force : Run full setup wizard even if already configured}
        {--skip-db : Skip database migration}
        {--skip-seed : Skip demo data seeding}';

    protected $description = 'Interactive setup wizard for new SaaS Kit installations';

    private string $envPath;

    private array $envValues = [];

    private array $status = [];

    public function handle(): int
    {
        $this->envPath = base_path('.env');

        $this->newLine();
        $this->components->info('Welcome to the SaaS Kit Setup Wizard.');
        $this->newLine();

        if (! File::exists($this->envPath)) {
            $this->components->error('.env file not found. Run: cp .env.example .env');

            return self::FAILURE;
        }

        $this->loadEnv();
        $this->detectStatus();

        if ($this->isConfigured() && ! $this->option('force')) {
            return $this->showConfiguredMenu();
        }

        return $this->runFullSetup();
    }

    private function detectStatus(): void
    {
        $this->status = [
            'app_name' => $this->envValues['APP_NAME'] ?? null,
            'app_key' => ! empty($this->envValues['APP_KEY']) && $this->envValues['APP_KEY'] !== 'base64:',
            'database' => $this->isDatabaseReady(),
            'provider' => $this->envValues['BILLING_DEFAULT_PROVIDER'] ?? null,
            'provider_configured' => $this->isProviderConfigured(),
            'admin_exists' => $this->adminExists(),
            'admin_email' => $this->getAdminEmail(),
            'user_count' => $this->getUserCount(),
            'product_count' => $this->getProductCount(),
        ];
    }

    private function isConfigured(): bool
    {
        return $this->status['app_key']
            && $this->status['database']
            && $this->status['admin_exists'];
    }

    private function showConfiguredMenu(): int
    {
        $this->showStatus();

        $this->newLine();

        $action = $this->choice(
            'What would you like to do?',
            [
                'billing' => 'Change billing provider',
                'admin' => 'Create another admin user',
                'sync' => 'Publish catalog to provider',
                'full' => 'Run full setup wizard',
                'exit' => 'Exit',
            ],
            'exit'
        );

        return match ($action) {
            'billing' => $this->menuChangeBilling(),
            'admin' => $this->menuCreateAdmin(),
            'sync' => $this->menuSyncProducts(),
            'full' => $this->runFullSetup(),
            default => self::SUCCESS,
        };
    }

    private function showStatus(): void
    {
        $appName = trim((string) ($this->status['app_name'] ?? 'Not set'), '"');
        $provider = ucfirst((string) ($this->status['provider'] ?? 'None'));
        $providerStatus = $this->status['provider_configured'] ? 'Ready' : 'Not configured';
        $adminEmail = $this->status['admin_email'] ?? 'None';
        $products = $this->status['product_count'] ?? 0;

        $this->components->twoColumnDetail('App Name', $appName);
        $this->components->twoColumnDetail('Database', $this->status['database'] ? 'Ready' : 'Not migrated');
        $this->components->twoColumnDetail('Billing Provider', "{$provider} {$providerStatus}");
        $this->components->twoColumnDetail('Admin User', $adminEmail);
        $this->components->twoColumnDetail('Products Synced', (string) $products);
    }

    private function menuChangeBilling(): int
    {
        $this->setupBillingProvider();
        $this->writeEnv();
        $this->clearCaches();

        $this->components->success('Billing provider updated.');

        if ($this->confirm('Publish the current catalog to the new provider?', true)) {
            return $this->menuSyncProducts();
        }

        return self::SUCCESS;
    }

    private function menuCreateAdmin(): int
    {
        $this->setupAdminUser();
        $this->components->success('Admin user created.');

        return self::SUCCESS;
    }

    private function menuSyncProducts(): int
    {
        $provider = $this->status['provider'] ?? 'stripe';

        $this->components->task("Publishing catalog to {$provider}", function () use ($provider) {
            Artisan::call(self::CATALOG_SYNC_COMMAND, [
                'provider' => $provider,
                '--apply' => true,
                '--update' => true,
            ]);

            return true;
        });

        $this->components->success('Catalog published.');

        return self::SUCCESS;
    }

    private function runFullSetup(): int
    {
        $this->setupApplication();
        $this->setupBillingProvider();

        if (! $this->option('skip-db')) {
            $this->setupDatabase();
        }

        $this->setupAdminUser();

        if (! $this->option('skip-seed')) {
            $this->setupDemoData();
        }

        $this->writeEnv();
        $this->runFinalCommands();

        $this->newLine();
        $this->components->success('Setup complete.');
        $this->newLine();

        $appUrl = $this->envValues['APP_URL'] ?? 'http://localhost:8000';
        $this->components->info("Visit your app: {$appUrl}");
        $this->components->info("Admin panel:   {$appUrl}/admin");
        $this->newLine();

        return self::SUCCESS;
    }

    private function isDatabaseReady(): bool
    {
        try {
            return Schema::hasTable('users') && Schema::hasTable('migrations');
        } catch (\Throwable) {
            return false;
        }
    }

    private function isProviderConfigured(): bool
    {
        $provider = $this->envValues['BILLING_DEFAULT_PROVIDER'] ?? 'stripe';

        return match ($provider) {
            'stripe' => ! empty($this->envValues['STRIPE_SECRET']),
            'paddle' => ! empty($this->envValues['PADDLE_API_KEY']) && ! empty($this->envValues['PADDLE_VENDOR_ID']),
            default => false,
        };
    }

    private function adminExists(): bool
    {
        try {
            if (! Schema::hasTable('users')) {
                return false;
            }

            return DB::table('users')->where('is_admin', true)->exists();
        } catch (\Throwable) {
            return false;
        }
    }

    private function getAdminEmail(): ?string
    {
        try {
            if (! Schema::hasTable('users')) {
                return null;
            }

            return DB::table('users')->where('is_admin', true)->value('email');
        } catch (\Throwable) {
            return null;
        }
    }

    private function getUserCount(): int
    {
        try {
            if (! Schema::hasTable('users')) {
                return 0;
            }

            return DB::table('users')->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function getProductCount(): int
    {
        try {
            if (! Schema::hasTable('products')) {
                return 0;
            }

            return DB::table('products')->where('is_active', true)->count();
        } catch (\Throwable) {
            return 0;
        }
    }

    private function loadEnv(): void
    {
        $content = File::get($this->envPath);

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (str_contains($line, '=')) {
                [$key, $value] = explode('=', $line, 2);
                $this->envValues[trim($key)] = trim($value, '"\'');
            }
        }
    }

    private function setupApplication(): void
    {
        $this->components->info('Application Settings');
        $this->newLine();

        $currentName = trim((string) ($this->envValues['APP_NAME'] ?? 'SaaS Kit'), '"');
        $appName = $this->ask('What is your application name?', $currentName);
        $this->envValues['APP_NAME'] = "\"{$appName}\"";

        $currentUrl = $this->envValues['APP_URL'] ?? 'http://localhost:8000';
        $appUrl = $this->ask('What is your application URL?', $currentUrl);
        $this->envValues['APP_URL'] = $appUrl;

        if (empty($this->envValues['APP_KEY']) || $this->envValues['APP_KEY'] === 'base64:') {
            $this->components->task('Generating application key', function () {
                Artisan::call('key:generate', ['--force' => true]);

                return true;
            });
        }

        $this->newLine();
    }

    private function setupBillingProvider(): void
    {
        $this->components->info('Billing Provider');
        $this->newLine();

        $provider = $this->choice(
            'Which billing provider will you use?',
            [
                'stripe' => 'Stripe (Credit cards, global)',
                'paddle' => 'Paddle (PayPal, handles VAT)',
            ],
            $this->envValues['BILLING_DEFAULT_PROVIDER'] ?? 'stripe'
        );

        $this->envValues['BILLING_DEFAULT_PROVIDER'] = $provider;

        match ($provider) {
            'stripe' => $this->setupStripe(),
            'paddle' => $this->setupPaddle(),
        };

        $showProviderChoice = $this->confirm(
            'Show provider choice tabs on pricing page? (Recommended: No for production)',
            false
        );
        $this->envValues['BILLING_PROVIDER_CHOICE_ENABLED'] = $showProviderChoice ? 'true' : 'false';

        $this->newLine();
    }

    private function setupStripe(): void
    {
        $this->components->info('  Stripe configuration');

        $currentSecret = $this->envValues['STRIPE_SECRET'] ?? '';
        $masked = $currentSecret ? Str::mask($currentSecret, '*', 7, -4) : '(not set)';

        if ($this->confirm("Configure Stripe API keys? Current: {$masked}", empty($currentSecret))) {
            $secret = $this->secret('Stripe Secret Key (sk_...)');
            if ($secret) {
                $this->envValues['STRIPE_SECRET'] = $secret;
            }

            $publishable = $this->secret('Stripe Publishable Key (pk_...)');
            if ($publishable) {
                $this->envValues['STRIPE_KEY'] = $publishable;
            }

            $webhook = $this->secret('Stripe Webhook Secret (whsec_...) [optional]');
            if ($webhook) {
                $this->envValues['STRIPE_WEBHOOK_SECRET'] = $webhook;
            }
        }
    }

    private function setupPaddle(): void
    {
        $this->components->info('  Paddle configuration');

        $currentEnvironment = $this->envValues['PADDLE_ENV']
            ?? $this->envValues['PADDLE_ENVIRONMENT']
            ?? 'sandbox';

        $environment = $this->choice(
            'Paddle environment?',
            ['sandbox' => 'Sandbox (testing)', 'production' => 'Production'],
            $currentEnvironment
        );
        $this->envValues['PADDLE_ENV'] = $environment;

        $currentKey = $this->envValues['PADDLE_API_KEY'] ?? '';
        $masked = $currentKey ? Str::mask($currentKey, '*', 7, -4) : '(not set)';

        if ($this->confirm("Configure Paddle API keys? Current: {$masked}", empty($currentKey))) {
            $vendorId = $this->ask('Paddle Vendor ID');
            if (! empty($vendorId)) {
                $this->envValues['PADDLE_VENDOR_ID'] = $vendorId;
            }

            $apiKey = $this->secret('Paddle API Key');
            if ($apiKey) {
                $this->envValues['PADDLE_API_KEY'] = $apiKey;
            }

            $clientToken = $this->secret('Paddle Client-Side Token');
            if ($clientToken) {
                $this->envValues['PADDLE_CLIENT_SIDE_TOKEN'] = $clientToken;
            }

            $webhookSecret = $this->secret('Paddle Webhook Secret [optional]');
            if ($webhookSecret) {
                $this->envValues['PADDLE_WEBHOOK_SECRET'] = $webhookSecret;
            }
        }
    }

    private function setupDatabase(): void
    {
        $this->components->info('Database');
        $this->newLine();

        if ($this->confirm('Run database migrations?', true)) {
            $this->components->task('Running migrations', function () {
                Artisan::call('migrate', ['--force' => true]);

                return true;
            });
        }

        $this->newLine();
    }

    private function setupAdminUser(): void
    {
        $this->components->info('Admin User');
        $this->newLine();

        if (! $this->confirm('Create an admin user?', true)) {
            return;
        }

        $name = $this->ask('Admin name', 'Admin');
        $email = $this->ask('Admin email', 'admin@example.com');
        $password = $this->secret('Admin password (min 8 characters)');

        if (! $password || strlen($password) < 8) {
            $this->components->warn('Password must be at least 8 characters. Skipping admin creation.');

            return;
        }

        $this->components->task('Creating admin user', function () use ($name, $email, $password) {
            $userClass = config('auth.providers.users.model', \App\Models\User::class);

            $user = $userClass::updateOrCreate(
                ['email' => $email],
                [
                    'name' => $name,
                    'password' => Hash::make($password),
                ]
            );

            $user->forceFill([
                'email_verified_at' => now(),
                'is_admin' => true,
            ])->save();

            return $user->exists;
        });

        $this->newLine();
    }

    private function setupDemoData(): void
    {
        $this->components->info('Demo Data');
        $this->newLine();

        if (! $this->confirm('Seed demo data? (sample content)', false)) {
            return;
        }

        $this->components->task('Seeding demo data', function () {
            Artisan::call('db:seed', ['--force' => true]);

            return true;
        });

        $this->newLine();
    }

    private function writeEnv(): void
    {
        $this->components->task('Updating .env file', function () {
            $content = File::get($this->envPath);

            foreach ($this->envValues as $key => $value) {
                if (preg_match("/^{$key}=.*/m", $content)) {
                    $content = preg_replace(
                        "/^{$key}=.*/m",
                        "{$key}={$value}",
                        $content
                    );
                } else {
                    $content .= "\n{$key}={$value}";
                }
            }

            File::put($this->envPath, $content);

            return true;
        });
    }

    private function clearCaches(): void
    {
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
    }

    private function runFinalCommands(): void
    {
        $this->components->task('Clearing caches', function () {
            $this->clearCaches();
            Artisan::call('view:clear');

            return true;
        });

        $provider = $this->envValues['BILLING_DEFAULT_PROVIDER'] ?? 'stripe';
        $hasKey = match ($provider) {
            'stripe' => ! empty($this->envValues['STRIPE_SECRET']),
            'paddle' => ! empty($this->envValues['PADDLE_API_KEY']) && ! empty($this->envValues['PADDLE_VENDOR_ID']),
            default => false,
        };

        if ($hasKey && $this->confirm('Publish the local catalog to the billing provider?', true)) {
            $this->components->task('Publishing catalog', function () use ($provider) {
                Artisan::call(self::CATALOG_SYNC_COMMAND, [
                    'provider' => $provider,
                    '--apply' => true,
                    '--update' => true,
                ]);

                return true;
            });
        }
    }
}
