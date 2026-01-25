<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Stripe\StripeClient;

/**
 * Archive all products across billing providers.
 *
 * This command archives (soft-deletes) products on Stripe, Paddle, and LemonSqueezy
 * so they won't be synced into the local database. Products are archived, not deleted,
 * to preserve historical data on the provider side.
 *
 * @example php artisan billing:archive-all --provider=stripe
 * @example php artisan billing:archive-all --provider=paddle
 * @example php artisan billing:archive-all --provider=all
 * @example php artisan billing:archive-all --provider=all --dry-run
 */
class ArchiveAllProducts extends Command
{
    protected $signature = 'billing:archive-all
        {--provider=all : Provider to archive from (stripe|paddle|lemonsqueezy|all)}
        {--dry-run : Preview what would be archived without making changes}
        {--include-prices : Also archive prices (Stripe only)}
        {--batch-size=10 : Number of items to process per batch}
        {--delay=500 : Delay in milliseconds between API calls}';

    protected $description = 'Archive all products (and optionally prices) from billing providers';

    private bool $dryRun = false;

    private int $batchSize = 10;

    private int $delayMs = 500;

    private int $archivedCount = 0;

    private int $skippedCount = 0;

    private int $errorCount = 0;

    public function handle(): int
    {
        $provider = $this->option('provider');
        $this->dryRun = (bool) $this->option('dry-run');
        $this->batchSize = (int) $this->option('batch-size');
        $this->delayMs = (int) $this->option('delay');

        if ($this->dryRun) {
            $this->warn('🔍 Dry run mode - no changes will be made');
            $this->newLine();
        }

        $this->warn('⚠️  This will ARCHIVE all products on the selected provider(s).');
        $this->warn('   Archived products will not sync to local database.');
        $this->newLine();

        if (! $this->dryRun && ! $this->confirm('Are you sure you want to continue?')) {
            $this->info('Cancelled.');

            return self::SUCCESS;
        }

        $success = true;

        if ($provider === 'all' || $provider === 'stripe') {
            $success = $this->archiveStripe() && $success;
        }

        if ($provider === 'all' || $provider === 'paddle') {
            $success = $this->archivePaddle() && $success;
        }

        if ($provider === 'all' || $provider === 'lemonsqueezy') {
            $success = $this->archiveLemonSqueezy() && $success;
        }

        $this->newLine();
        $this->info('📊 Summary:');
        $this->info("   Archived: {$this->archivedCount}");
        $this->info("   Skipped:  {$this->skippedCount}");
        if ($this->errorCount > 0) {
            $this->error("   Errors:   {$this->errorCount}");
        }

        return $success ? self::SUCCESS : self::FAILURE;
    }

    /**
     * Archive all Stripe products (and optionally prices).
     */
    private function archiveStripe(): bool
    {
        $this->info('📦 Archiving Stripe products...');

        $secret = config('services.stripe.secret');

        if (! $secret) {
            $this->error('  ❌ Stripe secret not configured');

            return false;
        }

        try {
            $client = new StripeClient($secret);

            // Archive products
            $this->info('  → Fetching active products...');
            $products = $client->products->all(['active' => true, 'limit' => 100]);
            $productCount = count($products->data);
            $this->info("  → Found {$productCount} active products");

            $bar = $this->output->createProgressBar($productCount);
            $bar->start();

            foreach ($products->data as $product) {
                $this->archiveStripeProduct($client, $product);
                $bar->advance();
                usleep($this->delayMs * 1000);
            }

            $bar->finish();
            $this->newLine();

            // Optionally archive prices
            if ($this->option('include-prices')) {
                $this->info('  → Fetching active prices...');
                $prices = $client->prices->all(['active' => true, 'limit' => 100]);
                $priceCount = count($prices->data);
                $this->info("  → Found {$priceCount} active prices");

                $bar = $this->output->createProgressBar($priceCount);
                $bar->start();

                foreach ($prices->data as $price) {
                    $this->archiveStripePrice($client, $price);
                    $bar->advance();
                    usleep($this->delayMs * 1000);
                }

                $bar->finish();
                $this->newLine();
            }

            $this->info('  ✓ Stripe archiving complete');

            return true;
        } catch (\Throwable $e) {
            $this->error("  ❌ Stripe error: {$e->getMessage()}");

            return false;
        }
    }

    private function archiveStripeProduct(StripeClient $client, $product): void
    {
        if ($this->dryRun) {
            $this->line("    Would archive: {$product->name} ({$product->id})");
            $this->archivedCount++;

            return;
        }

        try {
            $client->products->update($product->id, ['active' => false]);
            $this->archivedCount++;
        } catch (\Throwable $e) {
            $this->errorCount++;
            $this->newLine();
            $this->error("    ❌ Failed: {$product->id} - {$e->getMessage()}");
        }
    }

    private function archiveStripePrice(StripeClient $client, $price): void
    {
        if ($this->dryRun) {
            $this->line("    Would archive: {$price->id}");
            $this->archivedCount++;

            return;
        }

        try {
            $client->prices->update($price->id, ['active' => false]);
            $this->archivedCount++;
        } catch (\Throwable $e) {
            $this->errorCount++;
            $this->newLine();
            $this->error("    ❌ Failed: {$price->id} - {$e->getMessage()}");
        }
    }

    /**
     * Archive all Paddle products.
     */
    private function archivePaddle(): bool
    {
        $this->info('🏓 Archiving Paddle products...');

        $apiKey = config('services.paddle.api_key');
        $environment = config('services.paddle.environment', 'production');
        $baseUrl = $environment === 'sandbox' ? 'https://sandbox-api.paddle.com' : 'https://api.paddle.com';

        if (! $apiKey) {
            $this->error('  ❌ Paddle API key not configured');

            return false;
        }

        try {
            // Fetch all active products
            $this->info('  → Fetching active products...');
            $products = [];
            $nextUrl = "{$baseUrl}/products?status=active&per_page=100";

            do {
                $response = Http::withToken($apiKey)
                    ->acceptJson()
                    ->timeout(30)
                    ->get($nextUrl);

                if (! $response->successful()) {
                    throw new \RuntimeException('Failed to fetch products: '.$response->body());
                }

                $data = $response->json();
                $pageProducts = $data['data'] ?? [];

                if (count($pageProducts) === 0) {
                    break;
                }

                $products = array_merge($products, $pageProducts);
                $nextUrl = $data['meta']['pagination']['next'] ?? null;

                usleep(500000); // Rate limit protection
            } while ($nextUrl);

            $productCount = count($products);
            $this->info("  → Found {$productCount} active products");

            if ($productCount === 0) {
                $this->info('  ✓ No active products to archive');

                return true;
            }

            $bar = $this->output->createProgressBar($productCount);
            $bar->start();

            foreach ($products as $product) {
                $this->archivePaddleProduct($apiKey, $baseUrl, $product);
                $bar->advance();
                usleep($this->delayMs * 1000);
            }

            $bar->finish();
            $this->newLine();

            // Archive prices
            $this->info('  → Fetching active prices...');
            $prices = [];
            $nextUrl = "{$baseUrl}/prices?status=active&per_page=100";

            do {
                $response = Http::withToken($apiKey)
                    ->acceptJson()
                    ->timeout(30)
                    ->get($nextUrl);

                if (! $response->successful()) {
                    throw new \RuntimeException('Failed to fetch prices: '.$response->body());
                }

                $data = $response->json();
                $pagePrices = $data['data'] ?? [];

                if (count($pagePrices) === 0) {
                    break;
                }

                $prices = array_merge($prices, $pagePrices);
                $nextUrl = $data['meta']['pagination']['next'] ?? null;

                usleep(500000);
            } while ($nextUrl);

            $priceCount = count($prices);
            $this->info("  → Found {$priceCount} active prices");

            if ($priceCount > 0) {
                $bar = $this->output->createProgressBar($priceCount);
                $bar->start();

                foreach ($prices as $price) {
                    $this->archivePaddlePrice($apiKey, $baseUrl, $price);
                    $bar->advance();
                    usleep($this->delayMs * 1000);
                }

                $bar->finish();
                $this->newLine();
            }

            $this->info('  ✓ Paddle archiving complete');

            return true;
        } catch (\Throwable $e) {
            $this->error("  ❌ Paddle error: {$e->getMessage()}");

            return false;
        }
    }

    private function archivePaddleProduct(string $apiKey, string $baseUrl, array $product): void
    {
        $id = $product['id'];
        $name = $product['name'] ?? $id;

        if ($this->dryRun) {
            $this->line("    Would archive: {$name} ({$id})");
            $this->archivedCount++;

            return;
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->patch("{$baseUrl}/products/{$id}", [
                    'status' => 'archived',
                ]);

            if ($response->successful()) {
                $this->archivedCount++;
            } else {
                throw new \RuntimeException($response->body());
            }
        } catch (\Throwable $e) {
            $this->errorCount++;
            $this->newLine();
            $this->error("    ❌ Failed: {$id} - {$e->getMessage()}");
        }
    }

    private function archivePaddlePrice(string $apiKey, string $baseUrl, array $price): void
    {
        $id = $price['id'];

        if ($this->dryRun) {
            $this->line("    Would archive: {$id}");
            $this->archivedCount++;

            return;
        }

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->patch("{$baseUrl}/prices/{$id}", [
                    'status' => 'archived',
                ]);

            if ($response->successful()) {
                $this->archivedCount++;
            } else {
                throw new \RuntimeException($response->body());
            }
        } catch (\Throwable $e) {
            $this->errorCount++;
            $this->newLine();
            $this->error("    ❌ Failed: {$id} - {$e->getMessage()}");
        }
    }

    /**
     * Archive all LemonSqueezy products.
     */
    private function archiveLemonSqueezy(): bool
    {
        $this->info('🍋 Archiving LemonSqueezy products...');

        $apiKey = config('services.lemonsqueezy.api_key');
        $storeId = config('services.lemonsqueezy.store_id');

        if (! $apiKey || ! $storeId) {
            $this->error('  ❌ LemonSqueezy API key or store ID not configured');

            return false;
        }

        // Note: LemonSqueezy doesn't have a public API for archiving products
        // Products must be archived via their dashboard
        $this->warn('  ⚠ LemonSqueezy does not support archiving products via API.');
        $this->warn('  ⚠ Please archive products manually in the LemonSqueezy dashboard.');

        return true;
    }
}
