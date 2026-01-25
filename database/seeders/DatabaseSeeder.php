<?php

namespace Database\Seeders;

use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use App\Domain\Content\Models\BlogCategory;
use App\Domain\Content\Models\BlogPost;
use App\Domain\Content\Models\BlogTag;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $adminData = User::factory()->make([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'is_admin' => true,
            'email_verified_at' => now(),
        ])->toArray();

        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            $adminData
        );
        if (! $admin->password) {
            $admin->update(['password' => 'password']);
        }

        $customerData = User::factory()->make([
            'name' => 'Test Customer',
            'email' => 'test@example.com',
            'email_verified_at' => now(),
        ])->toArray();

        $customer = User::firstOrCreate(
            ['email' => 'test@example.com'],
            $customerData
        );
        if (! $customer->password) {
            $customer->update(['password' => 'password']);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'users.view',
            'users.create',
            'users.update',
            'users.delete',
            'users.impersonate',
            'roles.view',
            'roles.create',
            'roles.update',
            'roles.delete',
            'permissions.view',
            'permissions.create',
            'permissions.update',
            'permissions.delete',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => 'web',
        ]);

        $adminRole->syncPermissions($permissions);
        $admin->syncRoles([$adminRole]);

        $category = BlogCategory::firstOrCreate([
            'slug' => Str::slug('Product Updates'),
        ], [
            'name' => 'Product Updates',
        ]);

        $tag = BlogTag::firstOrCreate([
            'slug' => Str::slug('Release'),
        ], [
            'name' => 'Release',
        ]);

        $post = BlogPost::firstOrCreate([
            'slug' => 'welcome-to-saas-kit',
        ], [
            'title' => 'Welcome to SaaS Kit',
            'excerpt' => 'A quick tour of the architecture, billing model, and the admin experience.',
            'body_markdown' => <<<'MD'
# Shipping faster

This starter ships with B2C billing, auth, and a clear domain map.

## What is inside
- SSR-first UI with Filament
- Webhook-driven billing
- Customer-scoped entitlements

## Next steps
Wire in your billing provider IDs and start building.
MD,
            'status' => \App\Enums\PostStatus::Published,
            'published_at' => now(),
            'author_id' => $admin->id,
            'category_id' => $category->id,
        ]);

        $post->tags()->syncWithoutDetaching([$tag->id]);

        $this->call(BlogPostSeeder::class);

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
                'is_featured' => false,
                'features' => [
                    'Single-account access',
                    '2 GB storage',
                    'Email support',
                    'Core analytics',
                ],
                'entitlements' => [
                    'storage_limit_mb' => 2048,
                    'support_sla' => 'community',
                ],
            ],
            'growth' => [
                'name' => 'Growth',
                'summary' => 'Great for growing products.',
                'description' => 'Scale features as you grow.',
                'type' => 'subscription',
                'is_featured' => true,
                'features' => [
                    'Flexible monthly billing',
                    '10 GB storage',
                    'Priority email support',
                    'Advanced analytics',
                ],
                'entitlements' => [
                    'storage_limit_mb' => 10240,
                    'support_sla' => 'priority',
                ],
            ],
            'lifetime' => [
                'name' => 'Lifetime',
                'summary' => 'One-time purchase for solo builders.',
                'description' => 'Pay once, keep updates.',
                'type' => 'one_time',
                'is_featured' => false,
                'features' => [
                    'Pay once, keep updates',
                    'Single-account access',
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
            ['plan' => 'growth', 'key' => 'monthly', 'label' => 'Monthly', 'interval' => 'month', 'amount' => 5900, 'has_trial' => true, 'trial_interval' => 'day', 'trial_interval_count' => 14],
            ['plan' => 'growth', 'key' => 'yearly', 'label' => 'Yearly', 'interval' => 'year', 'amount' => 59000],
            ['plan' => 'lifetime', 'key' => 'lifetime', 'label' => 'One-time', 'interval' => 'once', 'amount' => 49900],
        ];

        $providers = ['stripe', 'paddle', 'lemonsqueezy'];

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

            // Create Mappings
            foreach ($providers as $provider) {
                $providerId = sprintf('%s_%s_%s', $provider, $definition['plan'], $definition['key']);

                $price->mappings()->updateOrCreate(
                    [
                        'provider' => $provider,
                    ],
                    [
                        'provider_id' => $providerId,
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
                'plan_keys' => ['starter', 'growth'],
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
                'plan_keys' => ['growth'],
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
