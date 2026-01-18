<?php

namespace App\Jobs;

use App\Domain\Billing\Services\BillingProviderManager;
use App\Domain\Billing\Models\Product;
use App\Domain\Billing\Models\ProductProviderMapping;
use App\Domain\Billing\Models\PriceProviderMapping;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Collection;

class BulkDeleteProductsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 1;
    public $timeout = 600;

    /**
     * @param array<int> $productIds
     */
    public function __construct(
        public array $productIds
    ) {}

    public function handle(BillingProviderManager $billing): void
    {
        Log::info('[BulkDeleteProductsJob] Starting bulk deletion for ' . count($this->productIds) . ' products.');

        // 1. Gather all remote IDs to archive
        $productMappings = ProductProviderMapping::whereIn('product_id', $this->productIds)->get();
        
        // Find all prices associated with these products
        $priceMappings = PriceProviderMapping::whereHas('price', function ($query) {
            $query->whereIn('product_id', $this->productIds);
        })->get();

        Log::info("[BulkDeleteProductsJob] Found {$productMappings->count()} product mappings and {$priceMappings->count()} price mappings to archive.");

        // 2. Archive Prices remotely
        foreach ($priceMappings as $mapping) {
            $this->archiveSafely($billing, $mapping->provider, 'price', $mapping->provider_id);
        }

        // 3. Archive Products remotely
        foreach ($productMappings as $mapping) {
            $this->archiveSafely($billing, $mapping->provider, 'product', $mapping->provider_id);
        }

        // 4. Delete locally
        // We use Query Builder delete to bypass the Observer, because we have already handled the archival manually above.
        // This prevents the Observer from firing 100+ separate jobs.
        Log::info('[BulkDeleteProductsJob] IDs to delete: ' . implode(', ', $this->productIds));
        
        $count = Product::whereIn('id', $this->productIds)->delete();

        Log::info("[BulkDeleteProductsJob] Bulk deletion complete. Deleted {$count} local records.");
    }

    private function archiveSafely(BillingProviderManager $billing, string $provider, string $type, string $id): void
    {
        try {
            $adapter = $billing->adapter($provider);
            if ($type === 'product') {
                $adapter->archiveProduct($id);
            } else {
                $adapter->archivePrice($id);
            }
            Log::info("[BulkDeleteProductsJob]   --> Archived {$type} {$id} remotely.");
        } catch (\Throwable $e) {
            // Log but don't fail the entire job. We prioritize deleting the local record.
            // In a real production system, you might want to soft-delete or mark as "trash" if remote fails.
            // For now, we follow the existing pattern of "attempt archive, log error if fails".
            Log::warning("[BulkDeleteProductsJob] Failed to archive {$type} {$id} on {$provider}: " . $e->getMessage());
        }
    }
}
