<?php

namespace App\Domain\Billing\Exports;

use App\Domain\Billing\Models\PriceProviderMapping;
use App\Domain\Billing\Models\Product;
use App\Domain\Billing\Models\ProductProviderMapping;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CatalogPublishService
{
    /**
     * @return array{summary: array<string, array<string, int>>, warnings: array<int, string>}
     */
    public function preview(string $provider, bool $updateExisting = false, ?array $productIds = null): array
    {
        return $this->sync($provider, false, $updateExisting, $productIds);
    }

    /**
     * @return array{summary: array<string, array<string, int>>, warnings: array<int, string>}
     */
    public function apply(string $provider, bool $updateExisting = false, ?array $productIds = null): array
    {
        return $this->sync($provider, true, $updateExisting, $productIds);
    }

    /**
     * @return array{summary: array<string, array<string, int>>, warnings: array<int, string>}
     */
    private function sync(string $provider, bool $apply, bool $updateExisting, ?array $productIds): array
    {
        $provider = strtolower($provider);
        $enabled = array_map('strtolower', config('saas.billing.providers', []));

        if (! in_array($provider, $enabled, true)) {
            throw new RuntimeException("Catalog publish provider [{$provider}] is not enabled.");
        }

        $adapter = $this->adapter($provider);
        $adapter->prepare();

        $summary = [
            'products' => ['create' => 0, 'update' => 0, 'skip' => 0],
            'prices' => ['create' => 0, 'update' => 0, 'skip' => 0, 'link' => 0],
        ];
        $warnings = [];

        $runner = function () use ($provider, $adapter, $apply, $updateExisting, $productIds, &$summary, &$warnings): void {
            $products = Product::query()
                ->with(['prices.mappings', 'providerMappings'])
                ->when($productIds, fn ($query) => $query->whereIn('id', $productIds))
                ->orderBy('id')
                ->get();

            foreach ($products as $product) {
                $productResult = $adapter->ensureProduct($product, $apply, $updateExisting);
                $productAction = $productResult['action'] ?? 'skip';
                if (isset($summary['products'][$productAction])) {
                    $summary['products'][$productAction]++;
                }

                $providerProductId = $productResult['id']
                    ?? $product->providerMappings
                        ->firstWhere('provider', $provider)
                        ?->provider_id;

                if ($apply && $providerProductId) {
                    DB::transaction(function () use ($product, $provider, $providerProductId) {
                        ProductProviderMapping::updateOrCreate(
                            ['product_id' => $product->id, 'provider' => $provider],
                            ['provider_id' => $providerProductId]
                        );
                    });
                }

                // For preview mode: still show prices that would be created
                // For apply mode: need actual product ID
                if (! $providerProductId && $apply) {
                    $warnings[] = "Product [{$product->key}] could not resolve a {$provider} product id.";

                    continue;
                }

                // Preview mode - use placeholder for price counting
                if (! $providerProductId && ! $apply) {
                    $providerProductId = 'preview_placeholder';
                }

                $productPrices = $product->prices;

                if ($productPrices->isEmpty()) {
                    $warnings[] = "Product [{$product->key}] has no prices to publish.";

                    continue;
                }

                foreach ($productPrices as $price) {
                    $priceResult = $adapter->ensurePrice($product, $price, $providerProductId, $apply, $updateExisting);
                    $priceAction = $priceResult['action'] ?? 'skip';
                    if (isset($summary['prices'][$priceAction])) {
                        $summary['prices'][$priceAction]++;
                    }

                    if ($apply && ! empty($priceResult['id'])) {
                        DB::transaction(function () use ($price, $provider, $priceResult) {
                            PriceProviderMapping::updateOrCreate(
                                ['price_id' => $price->id, 'provider' => $provider],
                                ['provider_id' => $priceResult['id']]
                            );
                        });
                    }
                }
            }
        };

        // REMOVED: DB::transaction wrapper to avoid holding locks during API calls
        $runner();

        return [
            'summary' => $summary,
            'warnings' => $warnings,
        ];
    }

    private function adapter(string $provider): CatalogPublishAdapter
    {
        return match ($provider) {
            'stripe' => app(StripeCatalogPublishAdapter::class),
            'paddle' => app(PaddleCatalogPublishAdapter::class),
            'lemonsqueezy' => app(LemonSqueezyCatalogPublishAdapter::class),
            default => throw new RuntimeException("Catalog publish provider [{$provider}] is not supported."),
        };
    }
}
