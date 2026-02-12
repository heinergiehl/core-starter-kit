<?php

namespace App\Console\Commands\Billing;

use App\Domain\Billing\Exports\CatalogPublishService;
use App\Domain\Billing\Models\PaymentProvider;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use App\Enums\PriceType;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

class SeedSubscriptionPlans extends Command
{
    protected $signature = 'billing:seed-subscription-plans
        {--publish : Publish generated plans to active providers after seeding}
        {--force : Run without confirmation in non-local environments}';

    protected $description = 'Seed three recurring subscription plans (monthly/yearly) for staging subscription testing.';

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

        $this->info('Seeding recurring subscription plans...');
        $this->line('Target keys: '.implode(', ', $planKeys));

        $products = [];

        Model::withoutEvents(function () use (&$products, $planDefinitions, $priceDefinitions): void {
            foreach ($planDefinitions as $planKey => $definition) {
                $products[$planKey] = Product::query()->updateOrCreate(
                    ['key' => $planKey],
                    [
                        'name' => $definition['name'],
                        'summary' => $definition['summary'],
                        'description' => $definition['description'],
                        'type' => PriceType::Recurring->value,
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
                    ->where(function ($query): void {
                        $query->where('type', PriceType::OneTime->value)
                            ->orWhere('interval', 'once');
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
                        'type' => PriceType::Recurring->value,
                        'has_trial' => false,
                        'trial_interval' => null,
                        'trial_interval_count' => null,
                        'is_active' => true,
                    ]
                );
            }
        });

        $this->table(
            ['Plan Key', 'Name', 'Monthly', 'Yearly'],
            collect($planDefinitions)->map(function (array $definition, string $planKey) use ($priceDefinitions): array {
                $monthly = collect($priceDefinitions)->first(fn (array $price): bool => $price['plan'] === $planKey && $price['key'] === 'monthly');
                $yearly = collect($priceDefinitions)->first(fn (array $price): bool => $price['plan'] === $planKey && $price['key'] === 'yearly');

                return [
                    $planKey,
                    $definition['name'],
                    $this->formatUsd($monthly['amount'] ?? 0),
                    $this->formatUsd($yearly['amount'] ?? 0),
                ];
            })->values()->all()
        );

        if ((bool) $this->option('publish')) {
            $this->publishCatalog(array_values(array_map(fn (Product $product): int => $product->id, $products)));
        } else {
            $this->line('Tip: run `php artisan billing:publish-catalog stripe --apply --update` and `php artisan billing:publish-catalog paddle --apply --update` to sync provider IDs.');
        }

        $this->info('Subscription plan seeding completed.');

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
            ->values()
            ->all();

        $keys = count($configured) >= 3
            ? array_slice($configured, 0, 3)
            : ['starter', 'pro', 'growth'];

        $planDefinitions = [
            $keys[0] => [
                'name' => 'Starter',
                'summary' => 'For early-stage products validating paid subscriptions.',
                'description' => 'Starter subscription with core billing and onboarding workflows.',
                'is_featured' => false,
                'features' => [
                    'Single active project',
                    'Subscription checkout and portal flows',
                    'Core analytics and reporting',
                    'Email support',
                ],
                'entitlements' => [
                    'project_limit' => 1,
                    'support_sla' => 'email',
                ],
            ],
            $keys[1] => [
                'name' => 'Pro',
                'summary' => 'For teams that need higher limits and faster iteration.',
                'description' => 'Balanced recurring plan for full subscription lifecycle testing.',
                'is_featured' => true,
                'features' => [
                    'Unlimited projects',
                    'Subscription plan switching',
                    'Advanced billing metrics',
                    'Priority email support',
                ],
                'entitlements' => [
                    'project_limit' => -1,
                    'support_sla' => 'priority',
                ],
            ],
            $keys[2] => [
                'name' => 'Growth',
                'summary' => 'For mature SaaS products with higher operational requirements.',
                'description' => 'Premium recurring plan for end-to-end subscription production rehearsals.',
                'is_featured' => false,
                'features' => [
                    'Unlimited projects',
                    'Highest entitlement tier',
                    'Team and role management',
                    'Priority support SLA',
                ],
                'entitlements' => [
                    'project_limit' => -1,
                    'support_sla' => 'priority_plus',
                ],
            ],
        ];

        $priceDefinitions = [
            ['plan' => $keys[0], 'key' => 'monthly', 'label' => 'Monthly', 'interval' => 'month', 'amount' => 2900],
            ['plan' => $keys[0], 'key' => 'yearly', 'label' => 'Yearly', 'interval' => 'year', 'amount' => 29000],
            ['plan' => $keys[1], 'key' => 'monthly', 'label' => 'Monthly', 'interval' => 'month', 'amount' => 5900],
            ['plan' => $keys[1], 'key' => 'yearly', 'label' => 'Yearly', 'interval' => 'year', 'amount' => 59000],
            ['plan' => $keys[2], 'key' => 'monthly', 'label' => 'Monthly', 'interval' => 'month', 'amount' => 9900],
            ['plan' => $keys[2], 'key' => 'yearly', 'label' => 'Yearly', 'interval' => 'year', 'amount' => 99000],
        ];

        return [$keys, $planDefinitions, $priceDefinitions];
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
