<?php

namespace Tests\Unit\Domain\Billing\Imports;

use App\Domain\Billing\Imports\CatalogImportService;
use App\Domain\Billing\Imports\StripeCatalogImportAdapter;
use App\Domain\Billing\Models\Product;
use App\Domain\Billing\Models\ProductProviderMapping;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class CatalogImportServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_links_existing_tombstone_mapping_to_new_product()
    {
        // 1. Create a tombstone mapping (provider_id exists, product_id is null)
        ProductProviderMapping::create([
            'provider' => 'stripe',
            'provider_id' => 'prod_new123',
            'product_id' => null, // Tombstone
        ]);

        $mockAdapter = Mockery::mock(StripeCatalogImportAdapter::class);
        $mockAdapter->shouldReceive('fetch')->andReturn([
            'items' => [[
                'product' => [
                    'key' => 'new-product',
                    'name' => 'New Product',
                    'provider_id' => 'prod_new123',
                ],
                'plan' => [],
                'prices' => [],
            ]],
            'warnings' => [],
        ]);

        // Bind mock to container
        $this->instance(StripeCatalogImportAdapter::class, $mockAdapter);

        $service = new CatalogImportService;

        // 2. Run the import
        $service->apply('stripe');

        // 3. Assert mapping is updated
        $product = Product::where('key', 'new-product')->first();
        $this->assertNotNull($product);

        $this->assertDatabaseHas('product_provider_mappings', [
            'provider' => 'stripe',
            'provider_id' => 'prod_new123',
            'product_id' => $product->id,
        ]);
    }
}
