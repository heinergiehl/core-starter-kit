<?php

namespace Tests\Unit\Domain\Billing\Imports;

use App\Domain\Billing\Imports\PaddleCatalogImportAdapter;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PaddleCatalogImportAdapterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Config::set('services.paddle.api_key', 'test-key');
        Config::set('services.paddle.environment', 'sandbox');
    }

    public function test_it_handles_pagination_correctly()
    {
        Http::fake([
            'https://sandbox-api.paddle.com/products*' => Http::sequence()
                ->push(['data' => [['id' => 'pro_1', 'name' => 'Product 1', 'status' => 'active']], 'meta' => ['pagination' => ['next' => 'https://sandbox-api.paddle.com/products?after=cursor1']]])
                ->push(['data' => [['id' => 'pro_2', 'name' => 'Product 2', 'status' => 'active']], 'meta' => ['pagination' => ['next' => null]]]),
            'https://sandbox-api.paddle.com/prices*' => Http::response(['data' => []]),
        ]);

        $adapter = new PaddleCatalogImportAdapter();
        $result = $adapter->fetch();

        $this->assertCount(2, $result['items']);
        $this->assertEquals('Product 1', $result['items'][0]['product']['name']);
        $this->assertEquals('Product 2', $result['items'][1]['product']['name']);
    }

    public function test_it_imports_free_prices()
    {
        Http::fake([
            'https://sandbox-api.paddle.com/products*' => Http::response([
                'data' => [['id' => 'pro_1', 'name' => 'Free Product', 'status' => 'active']],
                'meta' => ['pagination' => ['next' => null]]
            ]),
            'https://sandbox-api.paddle.com/prices*' => Http::response([
                'data' => [[
                    'id' => 'pri_1',
                    'product_id' => 'pro_1',
                    'description' => 'Free Price',
                    'unit_price' => ['amount' => '0', 'currency_code' => 'USD'],
                    'billing_cycle' => ['interval' => 'month', 'frequency' => 1],
                    'status' => 'active',
                ]],
                'meta' => ['pagination' => ['next' => null]]
            ]),
        ]);

        $adapter = new PaddleCatalogImportAdapter();
        $result = $adapter->fetch();

        $this->assertCount(1, $result['items']);
        $prices = $result['items'][0]['prices'];
        $this->assertCount(1, $prices);
        $this->assertEquals(0, $prices[0]['amount']);
    }

    public function test_it_parses_boolean_metadata_correctly()
    {
        Http::fake([
            'https://sandbox-api.paddle.com/products*' => Http::response([
                'data' => [[
                    'id' => 'pro_1',
                    'name' => 'Meta Product',
                    'status' => 'active',
                    'custom_data' => [
                        'seat_based' => '1',
                        'featured' => 'true',
                    ]
                ]],
                'meta' => ['pagination' => ['next' => null]]
            ]),
            'https://sandbox-api.paddle.com/prices*' => Http::response(['data' => []]),
        ]);

        $adapter = new PaddleCatalogImportAdapter();
        $result = $adapter->fetch();

        $plan = $result['items'][0]['plan'];
        $this->assertTrue($plan['seat_based']);
        $this->assertTrue($plan['is_featured']);
    }
}
