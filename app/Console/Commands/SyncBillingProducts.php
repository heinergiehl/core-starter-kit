<?php

namespace App\Console\Commands;

use App\Domain\Billing\Adapters\Stripe\Handlers\StripePriceHandler;
use App\Domain\Billing\Adapters\Stripe\Handlers\StripeProductHandler;
use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Stripe\StripeClient;

/**
 * Sync products and prices from billing providers.
 *
 * This command pulls product/price data from Stripe and LemonSqueezy
 * and syncs it to the local database. This is especially useful for
 * LemonSqueezy which doesn't have product/price webhooks.
 *
 * @example php artisan billing:sync-products
 * @example php artisan billing:sync-products --provider=stripe
 * @example php artisan billing:sync-products --provider=lemonsqueezy --dry-run
 */
class SyncBillingProducts extends Command
{
    protected $signature = 'billing:sync-products
        {--provider=all : Provider to sync from (stripe|lemonsqueezy|all)}
        {--dry-run : Preview changes without saving to database}';

    protected $description = 'Sync products and prices from billing providers (Stripe, LemonSqueezy)';

    private bool $dryRun = false;

    public function handle(): int
    {
        $provider = $this->option('provider');
        $this->dryRun = (bool) $this->option('dry-run');

        if ($this->dryRun) {
            $this->warn('🔍 Dry run mode - no changes will be saved');
            $this->newLine();
        }

        $success = true;

        if ($provider === 'all' || $provider === 'stripe') {
            $success = $this->syncStripe() && $success;
        }

        if ($provider === 'all' || $provider === 'lemonsqueezy') {
            $success = $this->syncLemonSqueezy() && $success;
        }

        $this->newLine();
        $this->info($this->dryRun ? '✅ Dry run complete' : '✅ Sync complete');

        return $success ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Sync products and prices from Stripe.
     */
    private function syncStripe(): bool
    {
        $this->info('📦 Syncing from Stripe...');

        $secret = config('services.stripe.secret');

        if (!$secret) {
            $this->error('  ❌ Stripe secret not configured');
            return false;
        }

        try {
            $client = new StripeClient($secret);
            $productHandler = new StripeProductHandler();
            $priceHandler = new StripePriceHandler();

            // Sync products
            $this->info('  → Fetching products...');
            $products = $client->products->all(['active' => true, 'limit' => 100]);
            $productCount = 0;

            foreach ($products as $product) {
                $this->syncStripeProduct($product->toArray(), $productHandler);
                $productCount++;
            }

            $this->info("  ✓ {$productCount} products synced");

            // Sync prices
            $this->info('  → Fetching prices...');
            $prices = $client->prices->all(['active' => true, 'limit' => 100]);
            $priceCount = 0;

            foreach ($prices as $price) {
                $this->syncStripePrice($price->toArray(), $priceHandler);
                $priceCount++;
            }

            $this->info("  ✓ {$priceCount} prices synced");

            return true;
        } catch (\Throwable $e) {
            $this->error("  ❌ Stripe sync failed: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Sync a single Stripe product.
     */
    private function syncStripeProduct(array $product, StripeProductHandler $handler): void
    {
        $name = $product['name'] ?? 'Unknown';

        if ($this->dryRun) {
            $this->line("    Would sync product: {$name}");
            return;
        }

        $handler->syncProduct($product);
    }

    /**
     * Sync a single Stripe price.
     */
    private function syncStripePrice(array $price, StripePriceHandler $handler): void
    {
        $id = $price['id'] ?? 'unknown';

        if ($this->dryRun) {
            $this->line("    Would sync price: {$id}");
            return;
        }

        $handler->syncPrice($price);
    }

    /**
     * Sync products and variants from LemonSqueezy.
     */
    private function syncLemonSqueezy(): bool
    {
        $this->info('🍋 Syncing from LemonSqueezy...');

        $apiKey = config('services.lemonsqueezy.api_key');
        $storeId = config('services.lemonsqueezy.store_id');

        if (!$apiKey || !$storeId) {
            $this->error('  ❌ LemonSqueezy API key or store ID not configured');
            return false;
        }

        try {
            // Sync products
            $this->info('  → Fetching products...');
            $products = $this->fetchLemonSqueezyResource('products', $apiKey, $storeId);
            $productCount = 0;

            foreach ($products as $product) {
                $this->syncLemonSqueezyProduct($product);
                $productCount++;
            }

            $this->info("  ✓ {$productCount} products synced");

            // Sync variants (these are like prices in LS)
            $this->info('  → Fetching variants...');
            $variants = $this->fetchLemonSqueezyResource('variants', $apiKey, $storeId);
            $variantCount = 0;

            foreach ($variants as $variant) {
                $this->syncLemonSqueezyVariant($variant);
                $variantCount++;
            }

            $this->info("  ✓ {$variantCount} variants synced");

            return true;
        } catch (\Throwable $e) {
            $this->error("  ❌ LemonSqueezy sync failed: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Fetch a resource from LemonSqueezy API.
     */
    private function fetchLemonSqueezyResource(string $resource, string $apiKey, string $storeId): array
    {
        $response = Http::withToken($apiKey)
            ->withHeaders(['Accept' => 'application/vnd.api+json'])
            ->get("https://api.lemonsqueezy.com/v1/{$resource}", [
                'filter[store_id]' => $storeId,
            ]);

        if (!$response->successful()) {
            throw new \RuntimeException("Failed to fetch {$resource}: " . $response->body());
        }

        return $response->json('data') ?? [];
    }

    /**
     * Sync a LemonSqueezy product.
     */
    private function syncLemonSqueezyProduct(array $product): void
    {
        $id = $product['id'] ?? null;
        $attributes = $product['attributes'] ?? [];
        $name = $attributes['name'] ?? 'Unknown';

        if (!$id) {
            return;
        }

        if ($this->dryRun) {
            $this->line("    Would sync product: {$name}");
            return;
        }

        $key = $this->generateKey($name, $id);

        Product::updateOrCreate(
            [
                'provider' => 'lemonsqueezy',
                'provider_id' => (string) $id,
            ],
            [
                'key' => $this->preserveOrGenerateKey(Product::class, 'lemonsqueezy', $id, $key),
                'name' => $name,
                'description' => $attributes['description'] ?? null,
                'is_active' => ($attributes['status'] ?? 'published') === 'published',
                'synced_at' => now(),
            ]
        );
    }

    /**
     * Sync a LemonSqueezy variant as a price.
     */
    private function syncLemonSqueezyVariant(array $variant): void
    {
        $id = $variant['id'] ?? null;
        $attributes = $variant['attributes'] ?? [];
        $name = $attributes['name'] ?? 'Default';

        if (!$id) {
            return;
        }

        if ($this->dryRun) {
            $this->line("    Would sync variant: {$name}");
            return;
        }

        // Get product ID from relationships
        $productId = $variant['relationships']['product']['data']['id'] ?? null;

        // Find or create plan for this product
        $plan = $this->resolveOrCreateLemonSqueezyPlan($productId);

        if (!$plan) {
            return;
        }

        $interval = $this->resolveLemonSqueezyInterval($attributes);
        $key = $this->generateKey($interval, $id);

        Price::updateOrCreate(
            [
                'provider' => 'lemonsqueezy',
                'provider_id' => (string) $id,
            ],
            [
                'plan_id' => $plan->id,
                'key' => $this->preserveOrGenerateKey(Price::class, 'lemonsqueezy', $id, $key),
                'label' => $name,
                'interval' => $interval,
                'interval_count' => $attributes['interval_count'] ?? 1,
                'currency' => strtoupper($attributes['currency'] ?? 'USD'),
                'amount' => $attributes['price'] ?? 0,
                'type' => ($attributes['is_subscription'] ?? false) ? 'recurring' : 'one_time',
                'has_trial' => ($attributes['trial_interval_count'] ?? 0) > 0,
                'trial_interval' => $attributes['trial_interval'] ?? null,
                'trial_interval_count' => $attributes['trial_interval_count'] ?? null,
                'is_active' => ($attributes['status'] ?? 'published') === 'published',
            ]
        );
    }

    /**
     * Resolve or create a plan for a LemonSqueezy product.
     */
    private function resolveOrCreateLemonSqueezyPlan(?string $productId): ?Plan
    {
        if (!$productId) {
            return null;
        }

        // Find existing plan
        $plan = Plan::query()
            ->where('provider', 'lemonsqueezy')
            ->where('provider_id', $productId)
            ->first();

        if ($plan) {
            return $plan;
        }

        // Find the product
        $product = Product::query()
            ->where('provider', 'lemonsqueezy')
            ->where('provider_id', $productId)
            ->first();

        if (!$product) {
            return null;
        }

        // Create a plan
        return Plan::create([
            'product_id' => $product->id,
            'key' => $product->key,
            'name' => $product->name,
            'description' => $product->description,
            'type' => 'subscription',
            'is_active' => $product->is_active,
            'provider' => 'lemonsqueezy',
            'provider_id' => $productId,
            'synced_at' => now(),
        ]);
    }

    /**
     * Resolve interval from LemonSqueezy variant attributes.
     */
    private function resolveLemonSqueezyInterval(array $attributes): string
    {
        if (!($attributes['is_subscription'] ?? false)) {
            return 'one_time';
        }

        return $attributes['interval'] ?? 'month';
    }

    /**
     * Generate a URL-friendly key.
     */
    private function generateKey(string $name, string $id): string
    {
        $slug = Str::slug($name);
        $suffix = Str::substr((string) $id, -6);

        return "{$slug}-{$suffix}";
    }

    /**
     * Preserve existing key or generate a new one.
     */
    private function preserveOrGenerateKey(string $model, string $provider, string $providerId, string $default): string
    {
        $existing = $model::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        return $existing?->key ?? $default;
    }
}
