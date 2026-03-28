<?php

namespace Database\Seeders;

use App\Domain\Billing\Exports\CatalogPublishService;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use Illuminate\Database\Eloquent\Model;
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
                'summary' => 'Best fit for agencies and studios shipping multiple client SaaS products.',
                'description' => 'One-time license for agencies that need unlimited commercial projects plus priority support.',
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
                    'Priority Email Support',
                    'Commercial Use Across Client Builds',
                    'Best Fit for Agencies & Studios',
                    'Unlimited Internal Team Usage',
                ],
                'entitlements' => [
                    'project_limit' => -1,
                    'support_sla' => 'priority',
                ],
            ],
        ];

        $priceDefinitions = [
            ['plan' => 'hobbyist', 'key' => 'lifetime', 'label' => 'One-time', 'interval' => 'once', 'amount' => 4999],
            ['plan' => 'indie', 'key' => 'lifetime', 'label' => 'One-time', 'interval' => 'once', 'amount' => 9999],
            ['plan' => 'agency', 'key' => 'lifetime', 'label' => 'One-time', 'interval' => 'once', 'amount' => 14999],
        ];

        $providers = ['stripe', 'paddle'];
        $products = [];

        // Seed canonical catalog records without dispatching observer-based provider sync jobs.
        Model::withoutEvents(function () use ($planDefinitions, $priceDefinitions, $providers, &$products): void {
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

            foreach ($priceDefinitions as $definition) {
                $product = $products[$definition['plan']] ?? null;

                if (! $product) {
                    continue;
                }

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

                foreach ($providers as $provider) {
                    $hasApiKey = $this->hasApiConfig($provider);
                    $placeholderId = sprintf('%s_%s_%s', $provider, $definition['plan'], $definition['key']);

                    if ($hasApiKey) {
                        $price->mappings()
                            ->where('provider', $provider)
                            ->where('provider_id', $placeholderId)
                            ->delete();

                        continue;
                    }

                    if (! $price->mappings()->where('provider', $provider)->exists()) {
                        $price->mappings()->create([
                            'provider' => $provider,
                            'provider_id' => $placeholderId,
                        ]);
                    }
                }
            }
        });

        $this->syncToProviders();
    }

    private function syncToProviders(): void
    {
        if (! App::environment('local') && ! App::environment('testing')) {
            return;
        }

        if (! config('saas.billing.seed.publish_to_providers', false)) {
            $this->command?->line('Skipping provider publish during seeding. Run billing:publish-catalog explicitly when needed.');

            return;
        }

        $publishService = app(CatalogPublishService::class);
        $enabledProviders = config('saas.billing.providers', []);

        foreach ($enabledProviders as $provider) {
            try {
                if ($this->hasApiConfig($provider)) {
                    $this->command->info("Auto-publishing catalog to {$provider}...");

                    $result = $publishService->apply($provider, true);

                    $created = $result['summary']['products']['create'] ?? 0;
                    $updated = $result['summary']['products']['update'] ?? 0;

                    $this->command->info("   OK: {$created} created, {$updated} updated.");
                }
            } catch (\Throwable $e) {
                $this->command->warn("   Warning: failed to publish to {$provider}: {$e->getMessage()}");
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
