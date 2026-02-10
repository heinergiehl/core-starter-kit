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
        $this->components->info('🚀 Welcome to SaaS Kit Setup Wizard!');
        $this->newLine();

        if (! File::exists($this->envPath)) {
            $this->components->error('.env file not found. Run: cp .env.example .env');

            return self::FAILURE;
        }

        // Load current env values
        $this->loadEnv();

        // Detect current configuration status
        $this->detectStatus();

        // If already configured and not forced, show menu
        if ($this->isConfigured() && ! $this->option('force')) {
            return $this->showConfiguredMenu();
        }

        // Run full setup wizard
        return $this->runFullSetup();
    }

    /**
     * Detect current configuration status.
     */
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

    /**
     * Check if the app is already configured.
     */
    private function isConfigured(): bool
    {
        return $this->status['app_key']
            && $this->status['database']
            && $this->status['admin_exists'];
    }

    /**
     * Show menu for already configured apps.
     */
    private function showConfiguredMenu(): int
    {
        // Show current status
        $this->showStatus();

        $this->newLine();

        $action = $this->choice(
            'What would you like to do?',
            [
                'billing' => '💳 Change billing provider',
                'admin' => '👤 Create another admin user',
                'sync' => '🔄 Sync products from provider',
                'full' => '🔧 Run full setup wizard',
                'exit' => '👋 Exit',
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

    /**
     * Show current configuration status.
     */
    private function showStatus(): void
    {
        $appName = trim((string) ($this->status['app_name'] ?? 'Not set'), '"');
        $provider = ucfirst((string) ($this->status['provider'] ?? 'None'));
        $providerStatus = $this->status['provider_configured'] ? '✓' : '⚠️ Not configured';
        $adminEmail = $this->status['admin_email'] ?? 'None';
        $products = $this->status['product_count'] ?? 0;

        $this->components->twoColumnDetail('App Name', $appName);
        $this->components->twoColumnDetail('Database', $this->status['database'] ? '✓ Ready' : '❌ Not migrated');
        $this->components->twoColumnDetail('Billing Provider', "{$provider} {$providerStatus}");
        $this->components->twoColumnDetail('Admin User', $adminEmail);
        $this->components->twoColumnDetail('Products Synced', (string) $products);
    }

    /**
     * Menu action: Change billing provider.
     */
    private function menuChangeBilling(): int
    {
        $this->setupBillingProvider();
        $this->writeEnv();
        $this->clearCaches();

        $this->components->success('✓ Billing provider updated!');

        if ($this->confirm('Sync products from new provider?', true)) {
            return $this->menuSyncProducts();
        }

        return self::SUCCESS;
    }

    /**
     * Menu action: Create admin user.
     */
    private function menuCreateAdmin(): int
    {
        $this->setupAdminUser();
        $this->components->success('✓ Admin user created!');

        return self::SUCCESS;
    }

    /**
     * Menu action: Sync products.
     */
    private function menuSyncProducts(): int
    {
        $provider = $this->status['provider'] ?? 'stripe';

        $this->components->task("Syncing products from {$provider}", function () use ($provider) {
            Artisan::call('billing:sync', ['--provider' => $provider]);

            return true;
        });

        $this->components->success('✓ Products synced!');

        return self::SUCCESS;
    }

    /**
     * Run the full setup wizard.
     */
    private function runFullSetup(): int
    {
        // Step 1: Application Settings
        $this->setupApplication();

        // Step 2: Billing Provider
        $this->setupBillingProvider();

        // Step 3: Database
        if (! $this->option('skip-db')) {
            $this->setupDatabase();
        }

        // Step 4: Admin User
        $this->setupAdminUser();

        // Step 5: Optional Demo Data
        if (! $this->option('skip-seed')) {
            $this->setupDemoData();
        }

        // Write all env changes
        $this->writeEnv();

        // Final steps
        $this->runFinalCommands();

        $this->newLine();
        $this->components->success('🎉 Setup complete!');
        $this->newLine();

        $appUrl = $this->envValues['APP_URL'] ?? 'http://localhost:8000';
        $this->components->info("Visit your app: {$appUrl}");
        $this->components->info("Admin panel:   {$appUrl}/admin");
        $this->newLine();

        return self::SUCCESS;
    }

    // ========================================
    // Status Detection Helpers
    // ========================================

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
            'paddle' => ! empty($this->envValues['PADDLE_API_KEY']),
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

    // ========================================
    // Setup Steps
    // ========================================

    private function loadEnv(): void
    {
        $content = File::get($this->envPath);

        foreach (explode("\n", $content) as $line) {
            $line = trim($line);
            if (empty($line) || str_starts_with($line, '#')) {
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
        $this->components->info('📝 Application Settings');
        $this->newLine();

        // App Name
        $currentName = trim((string) ($this->envValues['APP_NAME'] ?? 'SaaS Kit'), '"');
        $appName = $this->ask('What is your application name?', $currentName);
        $this->envValues['APP_NAME'] = "\"{$appName}\"";

        // App URL
        $currentUrl = $this->envValues['APP_URL'] ?? 'http://localhost:8000';
        $appUrl = $this->ask('What is your application URL?', $currentUrl);
        $this->envValues['APP_URL'] = $appUrl;

        // Generate app key if not set
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
        $this->components->info('💳 Billing Provider');
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

        // Provider-specific configuration
        match ($provider) {
            'stripe' => $this->setupStripe(),
            'paddle' => $this->setupPaddle(),
        };

        // Provider choice on pricing page
        $showProviderChoice = $this->confirm(
            'Show provider choice tabs on pricing page? (Recommended: No for production)',
            false
        );
        $this->envValues['BILLING_PROVIDER_CHOICE_ENABLED'] = $showProviderChoice ? 'true' : 'false';

        $this->newLine();
    }

    private function setupStripe(): void
    {
        $this->components->info('  → Stripe Configuration');

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
        $this->components->info('  → Paddle Configuration');

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
        $this->components->info('🗄️  Database');
        $this->newLine();

        $runMigrations = $this->confirm('Run database migrations?', true);

        if ($runMigrations) {
            $this->components->task('Running migrations', function () {
                Artisan::call('migrate', ['--force' => true]);

                return true;
            });
        }

        $this->newLine();
    }

    private function setupAdminUser(): void
    {
        $this->components->info('👤 Admin User');
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
        $this->components->info('🎭 Demo Data');
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
                // Check if key exists
                if (preg_match("/^{$key}=.*/m", $content)) {
                    // Update existing key
                    $content = preg_replace(
                        "/^{$key}=.*/m",
                        "{$key}={$value}",
                        $content
                    );
                } else {
                    // Add new key at end
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

        // Sync products if provider is configured
        $provider = $this->envValues['BILLING_DEFAULT_PROVIDER'] ?? 'stripe';
        $hasKey = match ($provider) {
            'stripe' => ! empty($this->envValues['STRIPE_SECRET']),
            'paddle' => ! empty($this->envValues['PADDLE_API_KEY']),
            default => false,
        };

        if ($hasKey && $this->confirm('Sync products from billing provider?', true)) {
            $this->components->task('Syncing products', function () use ($provider) {
                Artisan::call('billing:sync', ['--provider' => $provider]);

                return true;
            });
        }
    }
}
