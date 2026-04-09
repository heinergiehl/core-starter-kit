<?php

namespace App\Console\Commands\Billing;

use App\Domain\Billing\Exports\CatalogPublishService;
use App\Domain\Billing\Models\PaymentProvider;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use App\Enums\PriceType;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class SeedSubscriptionPlans extends Command
{
    protected $signature = 'billing:seed-subscription-plans
        {--publish : Publish generated plans to active providers after seeding}
        {--force : Run without confirmation in non-local environments}';

    protected $description = 'Seed configurable pricing plans for staging and checkout testing.';

    public function handle(): int
    {
        if (! $this->option('force') && ! app()->environment(['local', 'testing'])) {
            $confirmed = $this->confirm(
                'This command updates billing catalog products/prices for subscription testing. Continue?',
                false
            );

            if (! $confirmed) {
                $this->warn('Aborted.');

                return self::FAILURE;
            }
        }

        [$planKeys, $planDefinitions, $priceDefinitions] = $this->subscriptionCatalogBlueprint();

        $this->info('Seeding pricing plans...');
        $this->line('Target keys: '.implode(', ', $planKeys));

        $products = [];

        Model::withoutEvents(function () use (&$products, $planDefinitions, $priceDefinitions): void {
            foreach ($planDefinitions as $planKey => $definition) {
                // Determine if this plan has any recurring prices
                $isRecurring = collect($priceDefinitions)
                    ->where('plan', $planKey)
                    ->where('interval', '!=', 'once')
                    ->isNotEmpty();

                $products[$planKey] = Product::query()->updateOrCreate(
                    ['key' => $planKey],
                    [
                        'name' => $definition['name'],
                        'summary' => $definition['summary'],
                        'description' => $definition['description'],
                        'type' => $isRecurring ? PriceType::Recurring->value : PriceType::OneTime->value,
                        'is_featured' => $definition['is_featured'],
                        'features' => $definition['features'],
                        'entitlements' => $definition['entitlements'],
                        'is_active' => true,
                    ]
                );
            }

            foreach ($products as $product) {
                Price::query()
                    ->where('product_id', $product->id)
                    ->where(function ($query) use ($product): void {
                        if ($product->type === PriceType::OneTime->value) {
                            $query->where('type', PriceType::Recurring->value)
                                ->orWhere('interval', '!=', 'once');
                        } else {
                            $query->where('type', PriceType::OneTime->value)
                                ->orWhere('interval', 'once');
                        }
                    })
                    ->update(['is_active' => false]);
            }

            foreach ($priceDefinitions as $definition) {
                $product = $products[$definition['plan']] ?? null;

                if (! $product) {
                    continue;
                }

                Price::query()->updateOrCreate(
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
                        'type' => $definition['interval'] === 'once' ? PriceType::OneTime->value : PriceType::Recurring->value,
                        'has_trial' => false,
                        'trial_interval' => null,
                        'trial_interval_count' => null,
                        'allow_custom_amount' => $definition['allow_custom_amount'] ?? false,
                        'custom_amount_minimum' => $definition['custom_amount_minimum'] ?? null,
                        'custom_amount_default' => $definition['custom_amount_default'] ?? null,
                        'suggested_amounts' => isset($definition['suggested_amounts'])
                            ? json_encode($definition['suggested_amounts'])
                            : null,
                        'is_active' => true,
                    ]
                );
            }
        });

        $this->table(
            ['Plan Key', 'Name', 'One-time'],
            collect($planDefinitions)->map(function (array $definition, string $planKey) use ($priceDefinitions): array {
                $oneTime = collect($priceDefinitions)->first(
                    fn (array $price): bool => $price['plan'] === $planKey && $price['key'] === 'once'
                );

                return [
                    $planKey,
                    $definition['name'],
                    $this->formatUsd($oneTime['amount'] ?? 0),
                ];
            })->values()->all()
        );

        if ((bool) $this->option('publish')) {
            $this->publishCatalog(array_values(array_map(fn (Product $product): int => $product->id, $products)));
        } else {
            $this->line('Tip: run `php artisan billing:publish-catalog stripe --apply --update` and `php artisan billing:publish-catalog paddle --apply --update` to sync provider IDs.');
        }

        $this->info('Pricing plan seeding completed.');

        return self::SUCCESS;
    }

    /**
     * @return array{
     *   0: array<int, string>,
     *   1: array<string, array{name:string,summary:string,description:string,is_featured:bool,features:array<int,string>,entitlements:array<string,mixed>}>,
     *   2: array<int, array{plan:string,key:string,label:string,interval:string,amount:int}>
     * }
     */
    private function subscriptionCatalogBlueprint(): array
    {
        $configured = collect(config('saas.billing.pricing.shown_plans', []))
            ->map(fn ($value): string => strtolower(trim((string) $value)))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $keys = $configured !== []
            ? $configured
            : ['starter', 'pro', 'growth'];

        $featuredIndex = count($keys) > 1
            ? (int) floor((count($keys) - 1) / 2)
            : 0;

        $pwywPlanKey = strtolower(trim((string) config('saas.billing.pricing.pwyw_plan', 'supporter')));

        $planDefinitions = [];
        $priceDefinitions = [];

        foreach ($keys as $index => $planKey) {
            $isPwyw = $planKey === $pwywPlanKey;
            $template = $isPwyw ? $this->pwywPlanTemplate() : $this->planTemplate($index);

            $planDefinitions[$planKey] = [
                'name' => Str::of($planKey)->replace(['-', '_'], ' ')->title()->value(),
                'summary' => $template['summary'],
                'description' => $template['description'],
                'is_featured' => $index === $featuredIndex,
                'features' => $template['features'],
                'entitlements' => $template['entitlements'],
            ];

            if ($isPwyw) {
                $priceDefinitions[] = [
                    'plan' => $planKey,
                    'key' => 'once',
                    'label' => 'Pay what you want',
                    'interval' => 'once',
                    'amount' => 0,
                    'allow_custom_amount' => true,
                    'custom_amount_minimum' => 100,
                    'custom_amount_default' => 1000,
                    'suggested_amounts' => [500, 1000, 2000, 5000],
                ];
            } else {
                $priceDefinitions[] = [
                    'plan' => $planKey,
                    'key' => 'once',
                    'label' => 'One-time',
                    'interval' => 'once',
                    'amount' => $this->defaultAmountForTier($index),
                    'allow_custom_amount' => false,
                    'custom_amount_minimum' => null,
                    'custom_amount_default' => null,
                    'suggested_amounts' => null,
                ];
            }
        }

        return [$keys, $planDefinitions, $priceDefinitions];
    }

    /**
     * @return array{summary:string,description:string,features:array<int,string>,entitlements:array<string,mixed>}
     */
    private function pwywPlanTemplate(): array
    {
        return [
            'summary' => 'Support the project and pay what feels right to you.',
            'description' => 'Full access to everything — choose your own price, no strings attached.',
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
                'support_sla' => 'email',
            ],
        ];
    }

    /**
     * @return array{summary:string,description:string,features:array<int,string>,entitlements:array<string,mixed>}
     */
    private function planTemplate(int $index): array
    {
        $templates = [
            [
                'summary' => 'Perfect for solo developers shipping their first SaaS.',
                'description' => 'One-time license with core billing and onboarding workflows.',
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
                    'support_sla' => 'email',
                ],
            ],
            [
                'summary' => 'For serial shippers building an empire of apps.',
                'description' => 'One-time license for unlimited commercial projects.',
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
                    'support_sla' => 'priority',
                ],
            ],
            [
                'summary' => 'The ultimate toolkit for agencies and teams.',
                'description' => 'One-time license with multi-tenancy, team management, and priority support.',
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
                    'support_sla' => 'priority_plus',
                    'coming_soon' => true,
                ],
            ],
            [
                'summary' => 'Built for larger launches, advanced teams, and premium support needs.',
                'description' => 'One-time license with launch support, advanced collaboration, and enterprise-ready tooling.',
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
                    'Priority onboarding support',
                    'Architecture review sessions',
                    'Advanced team collaboration workflows',
                ],
                'entitlements' => [
                    'project_limit' => -1,
                    'support_sla' => 'white_glove',
                ],
            ],
        ];

        return $templates[min($index, count($templates) - 1)];
    }

    private function defaultAmountForTier(int $index): int
    {
        $amounts = [4999, 9999, 14999, 24999, 39999, 59999];

        if (isset($amounts[$index])) {
            return $amounts[$index];
        }

        $lastIndex = count($amounts) - 1;

        return $amounts[$lastIndex] + (($index - $lastIndex) * 20000);
    }

    /**
     * @param  array<int, int>  $productIds
     */
    private function publishCatalog(array $productIds): void
    {
        $providers = PaymentProvider::query()
            ->where('is_active', true)
            ->pluck('slug')
            ->map(fn ($slug): string => strtolower((string) $slug))
            ->filter()
            ->values()
            ->all();

        if ($providers === []) {
            $providers = collect(config('saas.billing.providers', []))
                ->map(fn ($provider): string => strtolower((string) $provider))
                ->filter()
                ->values()
                ->all();
        }

        if ($providers === []) {
            $this->warn('No active billing providers found. Skipping publish step.');

            return;
        }

        $service = app(CatalogPublishService::class);

        foreach ($providers as $provider) {
            try {
                $result = $service->apply($provider, true, $productIds);
                $summary = $result['summary'] ?? [];
                $this->info("Published recurring plans to {$provider}.");
                $this->line(sprintf(
                    '  products: %d create, %d update, %d skip',
                    $summary['products']['create'] ?? 0,
                    $summary['products']['update'] ?? 0,
                    $summary['products']['skip'] ?? 0
                ));
                $this->line(sprintf(
                    '  prices: %d create, %d update, %d link, %d skip',
                    $summary['prices']['create'] ?? 0,
                    $summary['prices']['update'] ?? 0,
                    $summary['prices']['link'] ?? 0,
                    $summary['prices']['skip'] ?? 0
                ));
            } catch (\Throwable $exception) {
                $this->warn("Publish to {$provider} failed: {$exception->getMessage()}");
            }
        }
    }

    private function formatUsd(int $amountMinor): string
    {
        return '$'.number_format($amountMinor / 100, 2);
    }
}
