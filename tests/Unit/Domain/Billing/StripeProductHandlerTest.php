<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Adapters\Stripe\Handlers\StripeProductHandler;
use App\Domain\Billing\Models\Product;
use App\Domain\Billing\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripeProductHandlerTest extends TestCase
{
    use RefreshDatabase;

    private StripeProductHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new StripeProductHandler();
    }

    public function test_handles_product_created_event(): void
    {
        $event = WebhookEvent::create([
            'provider' => 'stripe',
            'event_id' => 'evt_test_product_created',
            'type' => 'product.created',
            'payload' => [
                'type' => 'product.created',
                'data' => [
                    'object' => [
                        'id' => 'prod_TestProduct123',
                        'name' => 'Test Product',
                        'description' => 'A test product description',
                        'active' => true,
                    ],
                ],
            ],
        ]);

        $object = $event->payload['data']['object'];
        $this->handler->handle($event, $object);

        $this->assertDatabaseHas('products', [
            'provider' => 'stripe',
            'provider_id' => 'prod_TestProduct123',
            'name' => 'Test Product',
            'description' => 'A test product description',
            'is_active' => true,
        ]);
    }

    public function test_handles_product_updated_event(): void
    {
        // Create existing product
        Product::create([
            'key' => 'test-product-123456',
            'name' => 'Old Name',
            'provider' => 'stripe',
            'provider_id' => 'prod_UpdateTest123',
            'is_active' => true,
        ]);

        $event = WebhookEvent::create([
            'provider' => 'stripe',
            'event_id' => 'evt_test_product_updated',
            'type' => 'product.updated',
            'payload' => [
                'type' => 'product.updated',
                'data' => [
                    'object' => [
                        'id' => 'prod_UpdateTest123',
                        'name' => 'Updated Product Name',
                        'description' => 'Updated description',
                        'active' => true,
                    ],
                ],
            ],
        ]);

        $object = $event->payload['data']['object'];
        $this->handler->handle($event, $object);

        $this->assertDatabaseHas('products', [
            'provider' => 'stripe',
            'provider_id' => 'prod_UpdateTest123',
            'name' => 'Updated Product Name',
            'description' => 'Updated description',
            'key' => 'test-product-123456', // Key preserved
        ]);
    }

    public function test_handles_product_deleted_event(): void
    {
        // Create existing product
        Product::create([
            'key' => 'deleted-product-123456',
            'name' => 'Product To Delete',
            'provider' => 'stripe',
            'provider_id' => 'prod_DeleteTest123',
            'is_active' => true,
        ]);

        $event = WebhookEvent::create([
            'provider' => 'stripe',
            'event_id' => 'evt_test_product_deleted',
            'type' => 'product.deleted',
            'payload' => [
                'type' => 'product.deleted',
                'data' => [
                    'object' => [
                        'id' => 'prod_DeleteTest123',
                    ],
                ],
            ],
        ]);

        $object = $event->payload['data']['object'];
        $this->handler->handle($event, $object);

        // Product should be deactivated, not deleted
        $this->assertDatabaseHas('products', [
            'provider' => 'stripe',
            'provider_id' => 'prod_DeleteTest123',
            'is_active' => false,
        ]);
    }

    public function test_generates_unique_key_from_name_and_id(): void
    {
        $product = $this->handler->syncProduct([
            'id' => 'prod_KeyGenTest12',
            'name' => 'My Awesome Product',
            'active' => true,
        ]);

        $this->assertNotNull($product);
        $this->assertStringContainsString('my-awesome-product', $product->key);
        $this->assertStringContainsString('est12', $product->key); // Last 6 chars
    }

    public function test_preserves_existing_key_on_update(): void
    {
        // Create product with custom key
        Product::create([
            'key' => 'custom-key',
            'name' => 'Original Name',
            'provider' => 'stripe',
            'provider_id' => 'prod_PreserveKey12',
            'is_active' => true,
        ]);

        // Sync same product with different name
        $product = $this->handler->syncProduct([
            'id' => 'prod_PreserveKey12',
            'name' => 'New Name',
            'active' => true,
        ]);

        $this->assertEquals('custom-key', $product->key);
    }
}
