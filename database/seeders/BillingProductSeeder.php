<?php

namespace Database\Seeders;

use App\Domain\Billing\Exports\CatalogPublishService;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

class BillingProductSeeder extends Seeder
{
    public function run(): void
    {
        $planDefinitions = [
            'hobbyist' => [
                'name' => 'Hobbyist',
                'summary' => 'Perfect for solo developers shipping their first SaaS.',
                'description' => 'Everything you need to launch one production-ready application.',
                'type' => 'one_time',
                'is_featured' => false,
                'features' => [
                    '1 Commercial Project License',
                    'Complete Billing: Subscriptions & One-time',
                    'Payments via Stripe & Paddle',
                    'Beautiful, Conversion-Optimized Checkout',
                    'Fully Customizable Themes & UI Components',
                    'Secure Authentication & Social Login',
                    'SEO-Ready Blog & Content Engine',
                    'Full Admin Panel & User Dashboard',
                    'Developer-Friendly with Test Coverage',
                    'Analytics Dashboard (MRR, Churn, ARPU)',
                    'Email Provider Integrations (SES, Mailgun)',
                    'Discord Community & Email Support',
                    'Lifetime Updates Included',
                ],
                'entitlements' => [
                    'project_limit' => 1,
                    'support_sla' => 'community',
                ],
            ],
            'indie' => [
                'name' => 'Indie',
                'summary' => 'For serial shippers building an empire of apps.',
                'description' => 'Ship unlimited projects with no restrictions.',
                'type' => 'one_time',
                'is_featured' => true,
                'features' => [
                    'Unlimited Commercial Projects',
                    'Complete Billing: Subscriptions & One-time',
                    'Payments via Stripe & Paddle',
                    'Beautiful, Conversion-Optimized Checkout',
                    'Fully Customizable Themes & UI Components',
                    'Secure Authentication & Social Login',
                    'SEO-Ready Blog & Content Engine',
                    'Full Admin Panel & User Dashboard',
                    'Developer-Friendly with Test Coverage',
                    'Analytics Dashboard (MRR, Churn, ARPU)',
                    'Email Provider Integrations (SES, Mailgun)',
                    'Discord Community & Email Support',
                    'Lifetime Updates Included',
                ],
                'entitlements' => [
                    'project_limit' => -1,
                    'support_sla' => 'email',
                ],
            ],
            'agency' => [
                'name' => 'Agency',
                'summary' => 'The ultimate toolkit for agencies and teams.',
                'description' => 'Full multi-tenancy and team management capabilities.',
                'type' => 'one_time',
                'is_featured' => false,
                'features' => [
                    'Unlimited Commercial Projects',
                    'Complete Billing: Subscriptions & One-time',
                    'Payments via Stripe & Paddle',
                    'Beautiful, Conversion-Optimized Checkout',
                    'Fully Customizable Themes & UI Components',
                    'Secure Authentication & Social Login',
                    'SEO-Ready Blog & Content Engine',
                    'Full Admin Panel & User Dashboard',
                    'Developer-Friendly with Test Coverage',
                    'Analytics Dashboard (MRR, Churn, ARPU)',
                    'Email Provider Integrations (SES, Mailgun)',
                    'Discord Community & Email Support',
                    'Lifetime Updates Included',
                    'Full multi-tenancy support',
                    'Seat-based & flat-rate plans',
                    'User invitations',
                    'Team management, roles & permissions',
                ],
                'entitlements' => [
                    'project_limit' => -1,
                    'support_sla' => 'priority',
                ],
            ],
        ];

        $products = [];

        foreach ($planDefinitions as $key => $definition) {
            $products[$key] = Product::updateOrCreate(
                ['key' => $key],
                [
                    'name' => $definition['name'],
                    'summary' => $definition['summary'],
                    'description' => $definition['description'],
                    'type' => $definition['type'],
                    'is_featured' => $definition['is_featured'],
                    'features' => $definition['features'],
                    'entitlements' => $definition['entitlements'],
                    'is_active' => true,
                ]
            );
        }

        $priceDefinitions = [
            ['plan' => 'hobbyist', 'key' => 'lifetime', 'label' => 'One-time', 'interval' => 'once', 'amount' => 4999],
            ['plan' => 'indie', 'key' => 'lifetime', 'label' => 'One-time', 'interval' => 'once', 'amount' => 9999],
            ['plan' => 'agency', 'key' => 'lifetime', 'label' => 'One-time', 'interval' => 'once', 'amount' => 14999],
        ];

        $providers = ['stripe', 'paddle'];

        foreach ($priceDefinitions as $definition) {
            $product = $products[$definition['plan']] ?? null;

            if (! $product) {
                continue;
            }

            // Create Price (agnostic)
            $price = Price::updateOrCreate(
                [
                    'product_id' => $product->id,
                    'key' => $definition['key'],
                ],
                [
                    'label' => $definition['label'],
                    'interval' => $definition['interval'],
                    'interval_count' => 1,
                    'currency' => 'USD',
                    'amount' => $definition['amount'],
                    'type' => $definition['interval'] === 'once' ? 'one_time' : 'recurring',
                    'has_trial' => (bool) ($definition['has_trial'] ?? false),
                    'trial_interval' => $definition['trial_interval'] ?? null,
                    'trial_interval_count' => $definition['trial_interval_count'] ?? null,
                    'is_active' => true,
                ]
            );

            // Handle Provider Mappings
            foreach ($providers as $provider) {
                $hasApiKey = $this->hasApiConfig($provider);
                $placeholderId = sprintf('%s_%s_%s', $provider, $definition['plan'], $definition['key']);

                // If we have an API key, we should NOT use placeholders.
                // We delete any existing placeholder so that the Sync service can create/link the real ID.
                if ($hasApiKey) {
                    $price->mappings()
                        ->where('provider', $provider)
                        ->where('provider_id', $placeholderId)
                        ->delete();

                    continue;
                }

                // If we DON'T have an API key, we need a placeholder for the UI to work (even if checkout fails)
                if (! $price->mappings()->where('provider', $provider)->exists()) {
                    $price->mappings()->create([
                        'provider' => $provider,
                        'provider_id' => $placeholderId,
                    ]);
                }
            }
        }

        // Attempt to sync to providers if keys are present
        $this->syncToProviders();
    }

    private function syncToProviders(): void
    {
        // Only run sync in local dev or if explicitly requested in CI/Prod
        // to avoid accidental spamming of production accounts from a seeder in wrong env
        if (! App::environment('local') && ! App::environment('testing')) {
            return;
        }

        $publishService = app(CatalogPublishService::class);
        $enabledProviders = config('saas.billing.providers', []);

        foreach ($enabledProviders as $provider) {
            try {
                // Simple check if API keys exist to avoid mostly-error logs
                if ($this->hasApiConfig($provider)) {
                    $this->command->info("🔄 Auto-publishing catalog to {$provider}...");

                    $result = $publishService->apply($provider, true); // true = ensure existing are updated

                    $created = $result['summary']['products']['create'] ?? 0;
                    $updated = $result['summary']['products']['update'] ?? 0;

                    $this->command->info("   ✓ {$created} created, {$updated} updated.");
                }
            } catch (\Throwable $e) {
                $this->command->warn("   ⚠️ Failed to publish to {$provider}: {$e->getMessage()}");
            }
        }
    }

    private function hasApiConfig(string $provider): bool
    {
        return match ($provider) {
            'stripe' => ! empty(config('services.stripe.secret')),
            'paddle' => ! empty(config('services.paddle.api_key')),
            default => false,
        };
    }
}
