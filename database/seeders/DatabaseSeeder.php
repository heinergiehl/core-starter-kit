<?php

namespace Database\Seeders;

use App\Domain\Content\Models\BlogCategory;
use App\Domain\Content\Models\BlogPost;
use App\Domain\Content\Models\BlogTag;
use App\Enums\SystemRoleName;
use App\Models\User;
use App\Support\Authorization\PermissionGuardrails;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
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
        // ── Permissions & Roles (always seeded) ─────────────────────────
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $guard = PermissionGuardrails::guardName();
        $permissions = PermissionGuardrails::corePermissionNames();

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => $guard,
            ]);
        }

        $adminRole = Role::firstOrCreate([
            'name' => SystemRoleName::Admin->value,
            'guard_name' => $guard,
        ]);

        $adminRole->syncPermissions($permissions);

        // ── Demo users (local / testing only) ───────────────────────────
        // In production, create your admin via: php artisan app:create-admin
        $admin = null;

        if (app()->environment(['local', 'testing'])) {
            $adminData = User::factory()->make([
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'is_admin' => true,
                'email_verified_at' => now(),
            ])->toArray();

            $admin = User::firstOrCreate(
                ['email' => $adminData['email']],
                Arr::only($adminData, ['name', 'email', 'password', 'locale', 'onboarding_completed_at'])
            );
            $admin->forceFill([
                'is_admin' => true,
                'email_verified_at' => $adminData['email_verified_at'] ?? now(),
            ])->save();
            if (! $admin->password) {
                $admin->update(['password' => 'password']);
            }

            $admin->syncRoles([$adminRole]);

            $customerData = User::factory()->make([
                'name' => 'Test Customer',
                'email' => 'test@example.com',
                'email_verified_at' => now(),
            ])->toArray();

            $customer = User::firstOrCreate(
                ['email' => $customerData['email']],
                Arr::only($customerData, ['name', 'email', 'password', 'locale', 'onboarding_completed_at'])
            );
            $customer->forceFill([
                'email_verified_at' => $customerData['email_verified_at'] ?? now(),
            ])->save();
            if (! $customer->password) {
                $customer->update(['password' => 'password']);
            }
        }

        // ── Blog content (always seeded) ────────────────────────────────
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

        // Seed the welcome post only when an author exists (author_id is required)
        if ($admin) {
            $post = BlogPost::firstOrCreate(
                ['slug' => 'welcome-to-saas-kit'],
                [
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
                ],
            );

            $post->tags()->syncWithoutDetaching([$tag->id]);
        }

        $this->call(BlogPostSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call(PaymentProviderSeeder::class);
            $this->call(BillingProductSeeder::class);
        }
    }
}
