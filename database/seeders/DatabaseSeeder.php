<?php

namespace Database\Seeders;

use App\Domain\Content\Models\BlogCategory;
use App\Domain\Content\Models\BlogPost;
use App\Domain\Content\Models\BlogTag;
use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use App\Domain\Organization\Enums\TeamRole;
use App\Domain\Organization\Models\Team;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'is_admin' => true,
            'email_verified_at' => now(),
        ]);

        $adminTeam = Team::create([
            'name' => 'Platform Admins',
            'slug' => 'platform-admins',
            'owner_id' => $admin->id,
        ]);

        $adminTeam->members()->attach($admin->id, [
            'role' => TeamRole::Owner->value,
            'joined_at' => now(),
        ]);

        $admin->update(['current_team_id' => $adminTeam->id]);

        $customer = User::factory()->create([
            'name' => 'Test Customer',
            'email' => 'test@example.com',
            'email_verified_at' => now(),
        ]);

        $customerTeam = Team::create([
            'name' => 'Acme Studio',
            'slug' => 'acme-studio',
            'owner_id' => $customer->id,
        ]);

        $customerTeam->members()->attach($customer->id, [
            'role' => TeamRole::Owner->value,
            'joined_at' => now(),
        ]);

        $customer->update(['current_team_id' => $customerTeam->id]);

        $category = BlogCategory::create([
            'name' => 'Product Updates',
            'slug' => Str::slug('Product Updates'),
        ]);

        $tag = BlogTag::create([
            'name' => 'Release',
            'slug' => Str::slug('Release'),
        ]);

        $post = BlogPost::create([
            'title' => 'Welcome to SaaS Kit',
            'slug' => 'welcome-to-saas-kit',
            'excerpt' => 'A quick tour of the architecture, billing model, and the admin experience.',
            'body_markdown' => <<<'MD'
# Shipping faster

This starter ships with teams, billing, and a clear domain map.

## What is inside
- SSR-first UI with Filament
- Webhook-driven billing
- Team-scoped entitlements

## Next steps
Wire in your billing provider IDs and start building.
MD,
            'is_published' => true,
            'published_at' => now(),
            'author_id' => $admin->id,
            'category_id' => $category->id,
        ]);

        $post->tags()->attach($tag->id);

        if (app()->environment(['local', 'testing'])) {
            $this->seedBillingCatalog();
        }
    }

    private function seedBillingCatalog(): void
    {
        $planDefinitions = [
            'starter' => [
                'name' => 'Starter',
                'summary' => 'Solo founders validating demand.',
                'description' => 'Launch quickly with the essentials.',
                'type' => 'subscription',
                'seat_based' => false,
                'max_seats' => 3,
                'is_featured' => false,
                'features' => [
                    'Up to 3 team members',
                    '2 GB storage',
                    'Email support',
                    'Core analytics',
                ],
                'entitlements' => [
                    'storage_limit_mb' => 2048,
                    'support_sla' => 'community',
                ],
            ],
            'team' => [
                'name' => 'Team',
                'summary' => 'Seat-based billing for growing teams.',
                'description' => 'Scale seats with predictable billing.',
                'type' => 'subscription',
                'seat_based' => true,
                'max_seats' => null,
                'is_featured' => true,
                'features' => [
                    'Seat-based pricing that scales',
                    '10 GB storage',
                    'Audit log + team roles',
                    'Priority email support',
                ],
                'entitlements' => [
                    'storage_limit_mb' => 10240,
                    'support_sla' => 'priority',
                ],
            ],
            'lifetime' => [
                'name' => 'Lifetime',
                'summary' => 'One-time purchase for indie teams.',
                'description' => 'Pay once, keep updates.',
                'type' => 'one_time',
                'seat_based' => false,
                'max_seats' => 5,
                'is_featured' => false,
                'features' => [
                    'Pay once, keep updates',
                    'Up to 5 team members',
                    '5 GB storage',
                    'Priority bug fixes',
                ],
                'entitlements' => [
                    'storage_limit_mb' => 5120,
                    'support_sla' => 'email',
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
                    'seat_based' => $definition['seat_based'],
                    'max_seats' => $definition['max_seats'],
                    'is_featured' => $definition['is_featured'],
                    'features' => $definition['features'],
                    'entitlements' => $definition['entitlements'],
                    'is_active' => true,
                ]
            );
        }

        $priceDefinitions = [
            ['plan' => 'starter', 'key' => 'monthly', 'label' => 'Monthly', 'interval' => 'month', 'amount' => 2900],
            ['plan' => 'starter', 'key' => 'yearly', 'label' => 'Yearly', 'interval' => 'year', 'amount' => 29000],
            ['plan' => 'team', 'key' => 'monthly', 'label' => 'Monthly', 'interval' => 'month', 'amount' => 5900, 'has_trial' => true, 'trial_interval' => 'day', 'trial_interval_count' => 14],
            ['plan' => 'team', 'key' => 'yearly', 'label' => 'Yearly', 'interval' => 'year', 'amount' => 59000],
            ['plan' => 'lifetime', 'key' => 'lifetime', 'label' => 'One-time', 'interval' => 'once', 'amount' => 49900],
        ];

        $providers = ['stripe', 'paddle', 'lemonsqueezy'];

        foreach ($priceDefinitions as $definition) {
            $product = $products[$definition['plan']] ?? null;

            if (!$product) {
                continue;
            }

            foreach ($providers as $provider) {
                $providerId = sprintf('%s_%s_%s', $provider, $definition['plan'], $definition['key']);

                Price::updateOrCreate(
                    [
                        'product_id' => $product->id,
                        'provider' => $provider,
                        'provider_id' => $providerId,
                    ],
                    [
                        'key' => $definition['key'],
                        'label' => $definition['label'],
                        'interval' => $definition['interval'],
                        'interval_count' => 1,
                        'currency' => 'USD',
                        'amount' => $definition['amount'],
                        'type' => 'flat',
                        'has_trial' => (bool) ($definition['has_trial'] ?? false),
                        'trial_interval' => $definition['trial_interval'] ?? null,
                        'trial_interval_count' => $definition['trial_interval_count'] ?? null,
                        'is_active' => true,
                    ]
                );
            }
        }

        $discountDefinitions = [
            [
                'provider' => 'stripe',
                'code' => 'WELCOME20',
                'name' => 'Welcome 20%',
                'description' => 'Launch promo for new customers.',
                'provider_type' => 'coupon',
                'type' => 'percent',
                'amount' => 20,
                'max_redemptions' => 200,
                'plan_keys' => ['starter', 'team'],
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addDays(30),
            ],
            [
                'provider' => 'paddle',
                'code' => 'PADDLE10',
                'name' => 'Paddle 10%',
                'description' => 'Paddle launch discount.',
                'provider_type' => 'coupon',
                'type' => 'percent',
                'amount' => 10,
                'max_redemptions' => 100,
                'plan_keys' => ['starter'],
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addDays(30),
            ],
            [
                'provider' => 'lemonsqueezy',
                'code' => 'LEMON15',
                'name' => 'Lemon 15%',
                'description' => 'Lemon Squeezy promo.',
                'provider_type' => 'coupon',
                'type' => 'percent',
                'amount' => 15,
                'max_redemptions' => 150,
                'plan_keys' => ['team'],
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addDays(30),
            ],
            [
                'provider' => 'stripe',
                'code' => 'LIFETIME50',
                'name' => 'Lifetime $50',
                'description' => 'Fixed discount for lifetime plan.',
                'provider_type' => 'coupon',
                'type' => 'fixed',
                'amount' => 5000,
                'currency' => 'USD',
                'max_redemptions' => 50,
                'plan_keys' => ['lifetime'],
                'starts_at' => now()->subDay(),
                'ends_at' => now()->addDays(30),
            ],
        ];

        foreach ($discountDefinitions as $definition) {
            Discount::updateOrCreate(
                [
                    'provider' => $definition['provider'],
                    'code' => $definition['code'],
                ],
                [
                    'name' => $definition['name'],
                    'description' => $definition['description'],
                    'provider_type' => $definition['provider_type'],
                    'type' => $definition['type'],
                    'amount' => $definition['amount'],
                    'currency' => $definition['currency'] ?? null,
                    'max_redemptions' => $definition['max_redemptions'],
                    'plan_keys' => $definition['plan_keys'] ?? null,
                    'price_keys' => $definition['price_keys'] ?? null,
                    'starts_at' => $definition['starts_at'],
                    'ends_at' => $definition['ends_at'],
                    'is_active' => true,
                ]
            );
        }
    }
}
