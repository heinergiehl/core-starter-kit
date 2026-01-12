<?php

namespace App\Console\Commands;

use App\Domain\Billing\Adapters\Stripe\Handlers\StripePriceHandler;
use App\Domain\Billing\Adapters\Stripe\Handlers\StripeProductHandler;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Stripe\StripeClient;

/**
 * Sync products and prices from billing providers.
 *
 * This command pulls product/price data from Stripe, Paddle, and LemonSqueezy
 * and syncs it to the local database. This is especially useful for
 * LemonSqueezy which doesn't have product/price webhooks.
 *
 * @example php artisan billing:sync-products
 * @example php artisan billing:sync-products --provider=stripe
 * @example php artisan billing:sync-products --provider=paddle
 * @example php artisan billing:sync-products --provider=lemonsqueezy --dry-run
 */
class SyncBillingProducts extends Command
{
    protected $signature = 'billing:sync-products
        {--provider=all : Provider to sync from (stripe|paddle|lemonsqueezy|all)}
        {--dry-run : Preview changes without saving to database}';

    protected $description = 'Sync products and prices from billing providers (Stripe, Paddle, LemonSqueezy)';

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

        if ($provider === 'all' || $provider === 'paddle') {
            $success = $this->syncPaddle() && $success;
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
            // Fetch ALL products (active and inactive) so we can archive local ones if needed
            $products = $client->products->all(['limit' => 100]);
            $productCount = 0;

            foreach ($products as $product) {
                $this->syncStripeProduct($product->toArray(), $productHandler);
                $productCount++;
            }

            $this->info("  ✓ {$productCount} products synced");

            // Sync prices
            $this->info('  → Fetching prices...');
            // Fetch ALL prices (active and inactive)
            $prices = $client->prices->all(['limit' => 100]);
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
     * Sync products and prices from Paddle.
     */
    private function syncPaddle(): bool
    {
        $this->info('🏓 Syncing from Paddle...');

        $apiKey = config('services.paddle.api_key');

        if (!$apiKey) {
            $this->error('  ❌ Paddle API key not configured');
            return false;
        }

        try {
            // Sync products
            $this->info('  → Fetching products...');
            $productsResponse = Http::withToken($apiKey)
                ->acceptJson()
                ->get('https://api.paddle.com/products', [
                    'per_page' => 200,
                    'status' => 'active,archived',
                ]);

            if (!$productsResponse->successful()) {
                throw new \RuntimeException('Failed to fetch products: ' . $productsResponse->body());
            }

            $products = $productsResponse->json('data') ?? [];
            $productCount = 0;

            foreach ($products as $product) {
                $this->syncPaddleProduct($product);
                $productCount++;
            }

            $this->info("  ✓ {$productCount} products synced");

            // Sync prices
            $this->info('  → Fetching prices...');
            $pricesResponse = Http::withToken($apiKey)
                ->acceptJson()
                ->get('https://api.paddle.com/prices', [
                    'per_page' => 200,
                    'status' => 'active,archived',
                ]);

            if (!$pricesResponse->successful()) {
                throw new \RuntimeException('Failed to fetch prices: ' . $pricesResponse->body());
            }

            $prices = $pricesResponse->json('data') ?? [];
            $priceCount = 0;

            foreach ($prices as $price) {
                $this->syncPaddlePrice($price);
                $priceCount++;
            }

            $this->info("  ✓ {$priceCount} prices synced");

            return true;
        } catch (\Throwable $e) {
            $this->error("  ❌ Paddle sync failed: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Sync a Paddle product.
     */
    private function syncPaddleProduct(array $product): void
    {
        $id = $product['id'] ?? null;
        $name = $product['name'] ?? 'Unknown';

        if (!$id) {
            return;
        }

        if ($this->dryRun) {
            $this->line("    Would sync product: {$name}");
            return;
        }

        $customData = $product['custom_data'] ?? [];
        $key = $customData['plan_key'] ?? $customData['product_key'] ?? $this->generateKey($name, $id);

        // Ensure global uniqueness of the key (unless it belongs to THIS record)
        $finalKey = $this->ensureUniqueKey(Product::class, $this->preserveOrGenerateKey(Product::class, 'paddle', $id, $key), 'paddle', (string) $id);

        Product::updateOrCreate(
            [
                'provider' => 'paddle',
                'provider_id' => (string) $id,
            ],
            [
                'key' => $finalKey,
                'name' => $name,
                'description' => $product['description'] ?? null,
                'is_active' => ($product['status'] ?? 'active') === 'active',
                'synced_at' => now(),
            ]
        );
    }

    /**
     * Sync a Paddle price.
     */
    private function syncPaddlePrice(array $price): void
    {
        $id = $price['id'] ?? null;
        $productId = $price['product_id'] ?? null;

        if (!$id) {
            return;
        }

        if ($this->dryRun) {
            $this->line("    Would sync price: {$id}");
            return;
        }

        // Find or create product
        $product = $this->findPaddleProduct($productId);

        if (!$product) {
            return;
        }

        $customData = $price['custom_data'] ?? [];
        $billingCycle = $price['billing_cycle'] ?? [];
        $unitPrice = $price['unit_price'] ?? [];

        $interval = $billingCycle['interval'] ?? 'once';
        $intervalCount = (int) ($billingCycle['frequency'] ?? 1);
        $key = $customData['price_key'] ?? $this->resolvePaddlePriceKey($interval, $intervalCount);

        Price::updateOrCreate(
            [
                'provider' => 'paddle',
                'provider_id' => (string) $id,
            ],
            [
                'product_id' => $product->id,
                'key' => $this->preserveOrGenerateKey(Price::class, 'paddle', $id, $key),
                'label' => $price['description'] ?? ucfirst($key),
                'interval' => $interval,
                'interval_count' => $intervalCount,
                'currency' => strtoupper($unitPrice['currency_code'] ?? 'USD'),
                'amount' => (int) ($unitPrice['amount'] ?? 0),
                'type' => in_array($interval, ['day', 'week', 'month', 'year']) ? 'recurring' : 'one_time',
                'has_trial' => isset($price['trial_period']),
                'trial_interval' => $price['trial_period']['interval'] ?? null,
                'trial_interval_count' => $price['trial_period']['frequency'] ?? null,
                'is_active' => ($price['status'] ?? 'active') === 'active',
            ]
        );
    }

    /**
     * Find a product for a Paddle product ID.
     */
    private function findPaddleProduct(?string $productId): ?Product
    {
        if (!$productId) {
            return null;
        }

        return Product::query()
            ->where('provider', 'paddle')
            ->where('provider_id', $productId)
            ->first();
    }

    /**
     * Resolve price key from Paddle interval.
     */
    private function resolvePaddlePriceKey(string $interval, int $intervalCount): string
    {
        if ($interval === 'once') {
            return 'one_time';
        }

        if ($intervalCount === 1) {
            return match ($interval) {
                'month' => 'monthly',
                'year' => 'yearly',
                'week' => 'weekly',
                'day' => 'daily',
                default => $interval,
            };
        }

        return "every-{$intervalCount}-{$interval}";
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
            $products = $this->fetchLemonSqueezyResource('products', $apiKey, [
                'filter[store_id]' => $storeId,
            ]);
            $productCount = 0;

            foreach ($products as $product) {
                $this->syncLemonSqueezyProduct($product);
                $productCount++;
            }

            $this->info("  ✓ {$productCount} products synced");

            // Sync variants (these are like prices in LS)
            $this->info('  → Fetching variants...');
            $variants = $this->fetchLemonSqueezyResource('variants', $apiKey);
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
    private function fetchLemonSqueezyResource(string $resource, string $apiKey, array $query = []): array
    {
        $response = Http::withToken($apiKey)
            ->withHeaders(['Accept' => 'application/vnd.api+json'])
            ->get("https://api.lemonsqueezy.com/v1/{$resource}", $query);

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

        $key = $attributes['custom_data']['product_key'] ?? $this->generateKey($name, $id);

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

        // Find product for this variant
        $product = $this->findLemonSqueezyProduct($productId);

        if (!$product) {
            return;
        }

        $interval = $this->resolveLemonSqueezyInterval($attributes);
        $key = $attributes['custom_data']['price_key'] ?? $this->generateKey($interval, $id);

        Price::updateOrCreate(
            [
                'provider' => 'lemonsqueezy',
                'provider_id' => (string) $id,
            ],
            [
                'product_id' => $product->id,
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
     * Find a product for a LemonSqueezy product ID.
     */
    private function findLemonSqueezyProduct(?string $productId): ?Product
    {
        if (!$productId) {
            return null;
        }

        return Product::query()
            ->where('provider', 'lemonsqueezy')
            ->where('provider_id', $productId)
            ->first();
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
     * Ensure the key is unique across the table, excluding the current record.
     */
    private function ensureUniqueKey(string $model, string $key, string $provider, string $providerId): string
    {
        $original = $key;
        $counter = 1;

        while ($model::query()
            ->where('key', $key)
            ->where(function ($query) use ($provider, $providerId) {
                // Determine if this is a "different" record
                // If provider/provider_id match, it's the SAME record, so collision is fine (it's itself)
                $query->where('provider', '!=', $provider)
                      ->orWhere('provider_id', '!=', $providerId);
            })
            ->exists()) {
            $key = "{$original}-{$counter}";
            $counter++;
        }

        return $key;
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
