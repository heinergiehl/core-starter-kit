<?php

namespace App\Console\Commands;

use App\Domain\Billing\Adapters\Stripe\Handlers\StripePriceHandler;
use App\Domain\Billing\Adapters\Stripe\Handlers\StripeProductHandler;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\PriceProviderMapping;
use App\Domain\Billing\Models\Product;
use App\Domain\Billing\Models\ProductProviderMapping;
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
        {--dry-run : Preview changes without saving to database}
        {--force : Re-import products/prices even if deleted locally}';

    protected $description = 'Sync products and prices from billing providers (Stripe, Paddle, LemonSqueezy)';

    private bool $dryRun = false;
    private bool $allowImportDeleted = false;

    public function handle(): int
    {
        $provider = $this->option('provider');
        $this->dryRun = (bool) $this->option('dry-run');
        $this->allowImportDeleted = (bool) $this->option('force');

        if ($this->dryRun) {
            $this->warn('🔍 Dry run mode - no changes will be saved');
            $this->newLine();
        }
        if ($this->allowImportDeleted) {
            config(['saas.billing.allow_import_deleted' => true]);
            $this->warn('Force import enabled - deleted mappings may be re-imported');
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
        $environment = config('services.paddle.environment', 'production');
        $baseUrl = $environment === 'sandbox' ? 'https://sandbox-api.paddle.com' : 'https://api.paddle.com';

        if (!$apiKey) {
            $this->error('  ❌ Paddle API key not configured');
            return false;
        }

        try {
            // Sync products
            $this->info('  → Fetching products...');
            $nextUrl = "{$baseUrl}/products?per_page=100&status=active,archived";
            $productCount = 0;

            do {
                $productsResponse = Http::retry(3, 500)
                    ->timeout(30)
                    ->withToken($apiKey)
                    ->acceptJson()
                    ->get($nextUrl);

                if (!$productsResponse->successful()) {
                    throw new \RuntimeException('Failed to fetch products: ' . $productsResponse->body());
                }

                $data = $productsResponse->json();
                $products = $data['data'] ?? [];
                
                // CRITICAL: If we get 0 products, stop pagination (Paddle bug workaround)
                if (count($products) === 0) {
                    break;
                }
                
                // Pre-fetch all existing Paddle product mappings to avoid N+1 queries
                $existingMappings = ProductProviderMapping::where('provider', 'paddle')
                    ->pluck('product_id', 'provider_id');
                
                $page = 1;

                foreach ($products as $product) {
                    try {
                        $this->syncPaddleProduct($product, $existingMappings);
                        $productCount++;
                    } catch (\Throwable $e) {
                         $this->error("    ❌ Failed to sync product {$product['id']}: {$e->getMessage()}");
                    }
                }
                
                $nextUrl = $data['meta']['pagination']['next'] ?? null;
                $page++;
                
                // Small delay between pagination requests to avoid rate limiting
                if ($nextUrl) {
                    usleep(500000); // 0.5 seconds
                }
                
                if ($page > 50) {
                    $this->warn("  ⚠️ Safety limit reached (50 pages). Stopping sync.");
                    break;
                }
            } while ($nextUrl);

            $this->info("  ✓ {$productCount} products synced");

            // Sync prices
            $this->info('  → Fetching prices...');
            $nextUrl = "{$baseUrl}/prices?per_page=100&status=active,archived";
            $priceCount = 0;
            $skippedCount = 0;

            do {
                $pricesResponse = Http::retry(3, 500)
                    ->timeout(30)
                    ->withToken($apiKey)
                    ->acceptJson()
                    ->get($nextUrl);

                if (!$pricesResponse->successful()) {
                    throw new \RuntimeException('Failed to fetch prices: ' . $pricesResponse->body());
                }

                $data = $pricesResponse->json();
                $prices = $data['data'] ?? [];
                
                // CRITICAL: If we get 0 prices, stop pagination (Paddle bug workaround)
                if (count($prices) === 0) {
                    break;
                }
                
                // Pre-fetch all existing Paddle price mappings
                $existingPriceMappings = PriceProviderMapping::where('provider', 'paddle')
                    ->pluck('price_id', 'provider_id');

                foreach ($prices as $price) {
                    $synced = $this->syncPaddlePrice($price, $existingPriceMappings, $apiKey, $baseUrl);
                    if ($synced) {
                        $priceCount++;
                    } else {
                        $skippedCount++;
                    }
                }

                $nextUrl = $data['meta']['pagination']['next'] ?? null;
                
                // Small delay between pagination requests
                if ($nextUrl) {
                    usleep(500000); // 0.5 seconds
                }
            } while ($nextUrl);

            $this->info("  ✓ {$priceCount} prices synced" . ($skippedCount > 0 ? ", {$skippedCount} skipped" : ''));

            return true;
        } catch (\Throwable $e) {
            $this->error("  ❌ Paddle sync failed: {$e->getMessage()}");
            return false;
        }
    }

    /**
     * Sync a Paddle product.
     */
    private function syncPaddleProduct(array $product, $existingMappings): void
    {
        $id = $product['id'] ?? null;
        $name = $product['name'] ?? 'Unknown';

        if (!$id) {
            return;
        }

        // OPTIMIZATION: Check memory map first.
        // If it's NOT in our map (unmapped) AND it's archived, skip it immediately.
        // This avoids the DB query below.
        $isMapped = $existingMappings->has((string) $id);
        if (!$isMapped && ($product['status'] ?? 'active') !== 'active' && !$this->allowImportDeleted) {
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

        // Check for existing mapping
        $mapping = ProductProviderMapping::where('provider', 'paddle')
            ->where('provider_id', (string) $id)
            ->first();

        $productData = [
            'name' => $name,
            'description' => $product['description'] ?? null,
            'type' => 'subscription',
            'is_active' => strtolower($product['status'] ?? 'active') === 'active',
            'synced_at' => now(),
        ];

        if ($mapping) {
            if (!$mapping->product) {
                if (!$this->allowImportDeleted) {
                    return;
                }

                $productData['key'] = $finalKey;
                $productModel = Product::create($productData);
                $mapping->update(['product_id' => $productModel->id]);
                return;
            }

            $productModel = $mapping->product;
            // Update existing product
            $finalKey = $this->ensureUniqueKey(Product::class, $productModel->key, 'paddle', (string) $id);
             // Verify key hasn't changed or if it has, uniqueness
             if($productModel->key !== $finalKey) {
                 $productData['key'] = $finalKey;
             }
             
            $productModel->update($productData);
        } else {
            // Only import if active
            if (strtolower($product['status'] ?? 'active') !== 'active' && !$this->allowImportDeleted) {
                return;
            }

            // SMART LINKING: Check if product exists by name locally to avoid duplicates
            $existingProduct = Product::where('name', $name)->first();

            if ($existingProduct) {
                // Check if mapping already exists to prevent duplicates
                $existingMapping = ProductProviderMapping::where('provider', 'paddle')
                    ->where('provider_id', (string) $id)
                    ->first();
                
                if (!$existingMapping) {
                    $this->info("    🔗 Linked '{$name}' to existing local product.");
                    
                    // Create the missing mapping (only if it truly doesn't exist)
                    ProductProviderMapping::firstOrCreate([
                        'provider' => 'paddle',
                        'provider_id' => (string) $id,
                    ], [
                        'product_id' => $existingProduct->id,
                    ]);
                }

                // Update the local product with latest data (e.g. key/status)
                $finalKey = $this->ensureUniqueKey(Product::class, $existingProduct->key, 'paddle', (string) $id);
                if($existingProduct->key !== $finalKey) {
                     $productData['key'] = $finalKey;
                }
                $existingProduct->update($productData);
                return;
            }

            // Create new product
            $key = $customData['plan_key'] ?? $customData['product_key'] ?? $this->generateKey($name, $id);
            $finalKey = $this->ensureUniqueKey(Product::class, $key, 'paddle', (string) $id);
            
            $productData['key'] = $finalKey;
            
            $productModel = Product::create($productData);
            
            // Create mapping
            ProductProviderMapping::create([
                'product_id' => $productModel->id,
                'provider' => 'paddle',
                'provider_id' => (string) $id,
            ]);
        }
    }

    /**
     * Sync a Paddle price.
     * 
     * @return bool True if price was synced, false if skipped
     */
    private function syncPaddlePrice(array $price, $existingMappings, string $apiKey, string $baseUrl): bool
    {
        $id = $price['id'] ?? null;
        $productId = $price['product_id'] ?? null;

        if (!$id) {
            return false;
        }

        // DEBUG: Targeted logging for the problematic price
        $debug = false;
        if ($debug) {
            $this->warn("DEBUG: Processing target price {$id}");
            $this->warn("DEBUG: Status: " . ($price['status'] ?? 'unknown'));
            $this->warn("DEBUG: Product ID: {$productId}");
        }

        // OPTIMIZATION: Check memory map first.
        $isMapped = $existingMappings->has((string) $id);

        if ($debug) {
             $this->warn("DEBUG: isMapped: " . ($isMapped ? 'yes' : 'no'));
             $this->warn("DEBUG: allowImportDeleted: " . ($this->allowImportDeleted ? 'yes' : 'no'));
        }
        if (!$isMapped && ($price['status'] ?? 'active') !== 'active' && !$this->allowImportDeleted) {
            if ($debug) $this->warn("DEBUG: Skipped due to optimization check");
            return false;
        }

        if ($this->dryRun) {
            $this->line("    Would sync price: {$id}");
            return true;
        }

        // Find or create product
        $product = $this->findOrFetchPaddleProduct($productId, $apiKey, $baseUrl);

        if (!$product) {
            if ($debug) $this->warn("DEBUG: Skipped because product was not found/created");
            $this->warn("    ⚠ Skipped price {$id}: Product {$productId} not found");
            return false;
        } else {
            if ($debug) $this->warn("DEBUG: Product found: {$product->id}");
        }

        $customData = $price['custom_data'] ?? [];
        $billingCycle = $price['billing_cycle'] ?? [];
        $unitPrice = $price['unit_price'] ?? [];

        $interval = $billingCycle['interval'] ?? 'once';
        $intervalCount = (int) ($billingCycle['frequency'] ?? 1);
        $key = $customData['price_key'] ?? $this->resolvePaddlePriceKey($interval, $intervalCount);

        $mapping = PriceProviderMapping::where('provider', 'paddle')
             ->where('provider_id', (string) $id)
             ->first();

        $priceData = [
            'product_id' => $product->id,
            'label' => $price['description'] ?? ucfirst($key),
            'interval' => $interval,
            'interval_count' => $intervalCount,
            'currency' => strtoupper($unitPrice['currency_code'] ?? 'USD'),
            'amount' => (int) ($unitPrice['amount'] ?? 0),
            'type' => in_array($interval, ['day', 'week', 'month', 'year']) ? 'recurring' : 'one_time',
            'has_trial' => isset($price['trial_period']),
            'trial_interval' => $price['trial_period']['interval'] ?? null,
            'trial_interval_count' => $price['trial_period']['frequency'] ?? null,
            'is_active' => strtolower($price['status'] ?? 'active') === 'active',
        ];

        if ($debug) {
            $this->warn("DEBUG: priceData: " . json_encode($priceData));
        }

        try {
            if ($mapping) {
                if (!$mapping->price) {
                    if (!$this->allowImportDeleted) {
                        return false;
                    }

                    $finalKey = $this->ensureUniqueKey(Price::class, $key, 'paddle', (string) $id);
                    $priceData['key'] = $finalKey;
                    $priceModel = Price::create($priceData);
                    $mapping->update(['price_id' => $priceModel->id]);
                    return true;
                }

                $priceModel = $mapping->price;
                $finalKey = $this->ensureUniqueKey(Price::class, $priceModel->key, 'paddle', (string) $id);
                if($priceModel->key !== $finalKey) {
                     $priceData['key'] = $finalKey;
                }
                $priceModel->update($priceData);
                if ($debug) $this->warn("DEBUG: Updated existing price model {$priceModel->id}");
                return true;
            } else {
                // Only import if active
                if (strtolower($price['status'] ?? 'active') !== 'active' && !$this->allowImportDeleted) {
                    if ($debug) $this->warn("DEBUG: Skipped inactive price (Status: " . ($price['status'] ?? 'active') . ")");
                    return false;
                }

                $finalKey = $this->ensureUniqueKey(Price::class, $key, 'paddle', (string) $id);
                $priceData['key'] = $finalKey;

                $priceModel = Price::create($priceData);
                
                PriceProviderMapping::create([
                    'price_id' => $priceModel->id,
                    'provider' => 'paddle',
                    'provider_id' => (string) $id,
                ]);

                if ($debug) $this->warn("DEBUG: Created new price {$priceModel->id} and mapping for {$id}");
                return true;
            }
        } catch (\Exception $e) {
            $this->error("ERROR: Failed to save price/mapping for {$id}: " . $e->getMessage());
            return false;
        }

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
            ->whereHas('providerMappings', function ($query) use ($productId) {
                $query->where('provider', 'paddle')
                      ->where('provider_id', $productId);
            })
            ->first();
    }

    /**
     * Find product locally, or fetch from Paddle API if not found.
     */
    private function findOrFetchPaddleProduct(?string $productId, string $apiKey, string $baseUrl): ?Product
    {
        if (!$productId) {
            return null;
        }

        // First try to find locally
        $product = $this->findPaddleProduct($productId);
        
        if ($product) {
            return $product;
        }

        // Check if there's a mapping but product was deleted
        $existingMapping = ProductProviderMapping::where('provider', 'paddle')
            ->where('provider_id', (string) $productId)
            ->first();
            
        if ($existingMapping && !$existingMapping->product) {
            // Mapping exists but product was deleted
            if (!$this->allowImportDeleted) {
                $this->line("      (Product {$productId} was previously deleted locally)");
                return null;
            }
            
            // --force flag is set - restore the product by fetching from Paddle and linking to existing mapping
            try {
                $this->line("      Restoring deleted product {$productId}...");
                
                $response = Http::retry(2, 500)
                    ->timeout(15)
                    ->withToken($apiKey)
                    ->acceptJson()
                    ->get("{$baseUrl}/products/{$productId}");

                if ($response->successful()) {
                    $this->line("      API response for {$productId}: " . $response->status());
                } else {
                    $this->line("      API returned error: " . $response->body());
                    return null;
                }

                $paddleProduct = $response->json('data');
                
                if (!$paddleProduct) {
                    $this->line("      No product data in response. Data found: " . json_encode($response->json()));
                    return null;
                }

                $name = $paddleProduct['name'] ?? 'Unknown';
                $customData = $paddleProduct['custom_data'] ?? [];
                $key = $customData['plan_key'] ?? $customData['product_key'] ?? $this->generateKey($name, $productId);
                $finalKey = $this->ensureUniqueKey(Product::class, $key, 'paddle', (string) $productId);
                
                $productModel = Product::create([
                    'key' => $finalKey,
                    'name' => $name,
                    'description' => $paddleProduct['description'] ?? null,
                    'type' => 'subscription',
                    'is_active' => strtolower($paddleProduct['status'] ?? 'active') === 'active',
                    'synced_at' => now(),
                ]);
                
                // Update the existing mapping to point to the new product
                $existingMapping->update(['product_id' => $productModel->id]);
                
                $this->info("      ✓ Restored product: {$name}");
                return $productModel;
            } catch (\Throwable $e) {
                $this->warn("    ⚠ Failed to restore product {$productId}: {$e->getMessage()}");
                return null;
            }
        }

        // Not found locally - fetch from Paddle API and create
        try {
            $this->line("      Fetching product {$productId} from Paddle API...");
            
            $response = Http::retry(2, 500)
                ->timeout(15)
                ->withToken($apiKey)
                ->acceptJson()
                ->get("{$baseUrl}/products/{$productId}");

            if (!$response->successful()) {
                $this->line("      API returned status: {$response->status()}");
                return null;
            }

            $paddleProduct = $response->json('data');
            
            if (!$paddleProduct) {
                $this->line("      No product data in response");
                return null;
            }

            // Pre-fetch existing mappings (empty collection for new product)
            $existingMappings = ProductProviderMapping::where('provider', 'paddle')
                ->pluck('product_id', 'provider_id');

            // Sync the product
            $this->syncPaddleProduct($paddleProduct, $existingMappings);

            // Now find it
            $product = $this->findPaddleProduct($productId);
            
            if ($product) {
                $this->info("      ✓ Created product: {$product->name}");
            } else {
                $this->line("      Failed to create product locally");
            }
            
            return $product;
        } catch (\Throwable $e) {
            $this->warn("    ⚠ Failed to fetch product {$productId} from Paddle: {$e->getMessage()}");
            return null;
        }
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
            $nextUrl = "https://api.lemonsqueezy.com/v1/products?filter[store_id]={$storeId}&page[size]=100";
            $productCount = 0;

            do {
                $productsResponse = Http::retry(3, 500)
                    ->timeout(30)
                    ->withToken($apiKey)
                    ->withHeaders(['Accept' => 'application/vnd.api+json'])
                    ->get($nextUrl);

                if (!$productsResponse->successful()) {
                    throw new \RuntimeException('Failed to fetch products: ' . $productsResponse->body());
                }

                $data = $productsResponse->json();
                $products = $data['data'] ?? [];
                
                // CRITICAL: If we get 0 products, stop pagination (API bug workaround)
                if (count($products) === 0) {
                    break;
                }

                foreach ($products as $product) {
                    $this->syncLemonSqueezyProduct($product);
                    $productCount++;
                }

                $nextUrl = $data['links']['next'] ?? null;
                
                // Small delay between pagination requests
                if ($nextUrl) {
                    usleep(500000); // 0.5 seconds
                }
            } while ($nextUrl);

            $this->info("  ✓ {$productCount} products synced");

            // Sync variants (these are like prices in LS)
            $this->info('  → Fetching variants...');
            $nextUrl = "https://api.lemonsqueezy.com/v1/variants?page[size]=100";
            $variantCount = 0;

            do {
                $variantsResponse = Http::retry(3, 500)
                    ->timeout(30)
                    ->withToken($apiKey)
                    ->withHeaders(['Accept' => 'application/vnd.api+json'])
                    ->get($nextUrl);

                if (!$variantsResponse->successful()) {
                    throw new \RuntimeException('Failed to fetch variants: ' . $variantsResponse->body());
                }

                $data = $variantsResponse->json();
                $variants = $data['data'] ?? [];
                
                // CRITICAL: If we get 0 variants, stop pagination (API bug workaround)
                if (count($variants) === 0) {
                    break;
                }

                foreach ($variants as $variant) {
                    $this->syncLemonSqueezyVariant($variant);
                    $variantCount++;
                }

                $nextUrl = $data['links']['next'] ?? null;
                
                // Small delay between pagination requests
                if ($nextUrl) {
                    usleep(500000); // 0.5 seconds
                }
            } while ($nextUrl);

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

        // Check for existing mapping
        $mapping = ProductProviderMapping::where('provider', 'lemonsqueezy')
            ->where('provider_id', (string) $id)
            ->first();

        $productData = [
            'name' => $name,
            'description' => $attributes['description'] ?? null,
            'is_active' => ($attributes['status'] ?? 'published') === 'published',
            // 'synced_at' => now(), // syncing date is global on mapping or ignored on product? Using now() is fine for product update tracking
        ];

        if ($mapping) {
            if (!$mapping->product) {
                if (!$this->allowImportDeleted) {
                    return;
                }

                $key = $attributes['custom_data']['product_key'] ?? $this->generateKey($name, $id);
                $finalKey = $this->ensureUniqueKey(Product::class, $key, 'lemonsqueezy', (string) $id);
                $productData['key'] = $finalKey;
                $productModel = Product::create($productData);
                $mapping->update(['product_id' => $productModel->id]);
                return;
            }

            $productModel = $mapping->product;
            $productModel->update($productData);
        } else {
             $key = $attributes['custom_data']['product_key'] ?? $this->generateKey($name, $id);
             $finalKey = $this->ensureUniqueKey(Product::class, $key, 'lemonsqueezy', (string) $id);
             $productData['key'] = $finalKey;

             $productModel = Product::create($productData);

             ProductProviderMapping::create([
                 'product_id' => $productModel->id,
                 'provider' => 'lemonsqueezy',
                 'provider_id' => (string) $id,
             ]);
        }
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

        // Get product ID from attributes (primary) or relationships (fallback)
        // LemonSqueezy API includes product_id directly in attributes
        $productId = $attributes['product_id'] ?? $variant['relationships']['product']['data']['id'] ?? null;

        // Find product for this variant
        $product = $this->findLemonSqueezyProduct($productId);

        if (!$product) {
            $this->warn("      ⚠ Variant {$id}: Product {$productId} not found locally");
            return;
        }

        $interval = $this->resolveLemonSqueezyInterval($attributes);
        $key = $attributes['custom_data']['price_key'] ?? $this->generateKey($interval, $id);

        $mapping = PriceProviderMapping::where('provider', 'lemonsqueezy')
             ->where('provider_id', (string) $id)
             ->first();

        $priceData = [
            'product_id' => $product->id,
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
        ];

        if ($mapping) {
            if (!$mapping->price) {
                if (!$this->allowImportDeleted) {
                    return;
                }

                $key = $attributes['custom_data']['price_key'] ?? $this->generateKey($interval, $id);
                $finalKey = $this->ensureUniqueKey(Price::class, $key, 'lemonsqueezy', (string) $id);
                $priceData['key'] = $finalKey;
                $priceModel = Price::create($priceData);
                $mapping->update(['price_id' => $priceModel->id]);
                return;
            }

            // Update
             $priceModel = $mapping->price;
             $priceModel->update($priceData);
        } else {
             $key = $attributes['custom_data']['price_key'] ?? $this->generateKey($interval, $id);
             $finalKey = $this->ensureUniqueKey(Price::class, $key, 'lemonsqueezy', (string) $id);
             $priceData['key'] = $finalKey;
             
             $priceModel = Price::create($priceData);
             
             PriceProviderMapping::create([
                 'price_id' => $priceModel->id,
                 'provider' => 'lemonsqueezy',
                 'provider_id' => (string) $id,
             ]);
        }
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
            ->whereHas('providerMappings', function ($query) use ($productId) {
                 $query->where('provider', 'lemonsqueezy')
                       ->where('provider_id', $productId);
            })
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
        // Check if the desired key is already available (not taken, or taken by THIS record)
        $query = $model::query();

        if (method_exists($model, 'withTrashed')) {
            $query->withTrashed();
        }

        $query->where('key', $key)
            ->when($providerId, function($q) use ($provider, $providerId, $model) {
                // Ignore if it's the SAME record (checks both Product and Price mappings)
                if ($model === Product::class) {
                     $q->whereDoesntHave('providerMappings', function ($mq) use ($provider, $providerId) {
                         $mq->where('provider', $provider)
                            ->where('provider_id', $providerId);
                     });
                } elseif ($model === Price::class) {
                     $q->whereDoesntHave('mappings', function ($mq) use ($provider, $providerId) {
                        $mq->where('provider', $provider)
                           ->where('provider_id', $providerId);
                    });
                }
            });

        if (!$query->exists()) {
            return $key;
        }

        $original = $key;

        // If taken, find all keys starting with "{$original}-" to determine the next suffix
        // We use a raw query or simple LIKE to fetch potential collisions
        // Limit to 1000 collisions to avoid memory issues in extreme cases, though unlikely.
        $collisionQuery = $model::query();
        if (method_exists($model, 'withTrashed')) {
             $collisionQuery->withTrashed();
        }

        $collisions = $collisionQuery
            ->where('key', 'LIKE', "{$original}-%")
            ->pluck('key')
            ->toArray();

        // Convert collisions to a lookup set for O(1) checking
        $taken = array_flip($collisions);
        $counter = 1;

        while (isset($taken["{$original}-{$counter}"])) {
            $counter++;
        }

        return "{$original}-{$counter}";
    }

    /**
     * Preserve existing key or generate a new one.
     */
    private function preserveOrGenerateKey(string $model, string $provider, string $providerId, string $default): string
    {
        if ($model === Product::class) {
             $mapping = ProductProviderMapping::where('provider', $provider)
                ->where('provider_id', $providerId)
                ->first();
             return ($mapping && $mapping->product) ? $mapping->product->key : $default;
        }

        if ($model === Price::class) {
             $mapping = PriceProviderMapping::where('provider', $provider)
                ->where('provider_id', $providerId)
                ->first();
             return ($mapping && $mapping->price) ? $mapping->price->key : $default;
        }

        // Fallback for models not yet refactored (none?)
        // The query below would fail for Price/Product now as columns dropped.
        if ($model === Product::class || $model === Price::class) {
            return $default;
        }
        
        $existing = $model::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first();

        return $existing?->key ?? $default;
    }
}
