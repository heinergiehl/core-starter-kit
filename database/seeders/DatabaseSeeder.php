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
            $this->call(PaymentProviderSeeder::class);
            $this->call(BillingProductSeeder::class);
        }
    }
}
