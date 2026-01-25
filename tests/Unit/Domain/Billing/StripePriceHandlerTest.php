<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Adapters\Stripe\Handlers\StripePriceHandler;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use App\Domain\Billing\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StripePriceHandlerTest extends TestCase
{
    use RefreshDatabase;

    private StripePriceHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->handler = new StripePriceHandler;
    }

    public function test_handles_price_created_event(): void
    {
        // Create product first
        $product = Product::create([
            'key' => 'test-product-abc123',
            'name' => 'Test Product',
            'is_active' => true,
        ]);

        \App\Domain\Billing\Models\ProductProviderMapping::create([
            'product_id' => $product->id,
            'provider' => 'stripe',
            'provider_id' => 'prod_PriceTest123',
        ]);

        $event = WebhookEvent::create([
            'provider' => 'stripe',
            'event_id' => 'evt_test_price_created',
            'type' => 'price.created',
            'payload' => [
                'type' => 'price.created',
                'data' => [
                    'object' => [
                        'id' => 'price_TestPrice123',
                        'product' => 'prod_PriceTest123',
                        'currency' => 'usd',
                        'unit_amount' => 2900,
                        'type' => 'recurring',
                        'active' => true,
                        'recurring' => [
                            'interval' => 'month',
                            'interval_count' => 1,
                        ],
                    ],
                ],
            ],
        ]);

        $object = $event->payload['data']['object'];
        $this->handler->handle($event, $object);

        $this->assertDatabaseHas('prices', [
            'product_id' => $product->id,
            'currency' => 'USD',
            'amount' => 2900,
            'interval' => 'month',
            'interval_count' => 1,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('price_provider_mappings', [
            'provider' => 'stripe',
            'provider_id' => 'price_TestPrice123',
        ]);
    }

    public function test_handles_price_deleted_event(): void
    {
        // Create product and price
        $product = Product::create([
            'key' => 'test-product-del123',
            'name' => 'Test Product',
            'is_active' => true,
        ]);

        \App\Domain\Billing\Models\ProductProviderMapping::create([
            'product_id' => $product->id,
            'provider' => 'stripe',
            'provider_id' => 'prod_DeletePrice',
        ]);

        $price = Price::create([
            'product_id' => $product->id,
            'key' => 'monthly-del123',
            'interval' => 'month',
            'currency' => 'USD',
            'amount' => 1000,
            'is_active' => true,
        ]);

        \App\Domain\Billing\Models\PriceProviderMapping::create([
            'price_id' => $price->id,
            'provider' => 'stripe',
            'provider_id' => 'price_ToDelete123',
        ]);

        $event = WebhookEvent::create([
            'provider' => 'stripe',
            'event_id' => 'evt_test_price_deleted',
            'type' => 'price.deleted',
            'payload' => [
                'type' => 'price.deleted',
                'data' => [
                    'object' => [
                        'id' => 'price_ToDelete123',
                    ],
                ],
            ],
        ]);

        $object = $event->payload['data']['object'];
        $this->handler->handle($event, $object);

        // Price should be deactivated, not deleted
        $this->assertDatabaseHas('prices', [
            'id' => $price->id,
            'is_active' => false,
        ]);
    }

    public function test_generates_human_readable_label(): void
    {
        $product = Product::create([
            'key' => 'label-test-abc123',
            'name' => 'Label Test',
            'is_active' => true,
        ]);

        \App\Domain\Billing\Models\ProductProviderMapping::create([
            'product_id' => $product->id,
            'provider' => 'stripe',
            'provider_id' => 'prod_LabelTest123',
        ]);

        $price = $this->handler->syncPrice([
            'id' => 'price_LabelTest123',
            'product' => 'prod_LabelTest123',
            'currency' => 'usd',
            'unit_amount' => 9900,
            'type' => 'recurring',
            'active' => true,
            'recurring' => [
                'interval' => 'year',
                'interval_count' => 1,
            ],
        ]);

        $this->assertNotNull($price);
        $this->assertEquals('Yearly', $price->label);
    }

    public function test_handles_one_time_price(): void
    {
        $product = Product::create([
            'key' => 'onetime-test-abc123',
            'name' => 'One Time Test',
            'is_active' => true,
        ]);

        \App\Domain\Billing\Models\ProductProviderMapping::create([
            'product_id' => $product->id,
            'provider' => 'stripe',
            'provider_id' => 'prod_OneTime123',
        ]);

        $price = $this->handler->syncPrice([
            'id' => 'price_OneTime123',
            'product' => 'prod_OneTime123',
            'currency' => 'eur',
            'unit_amount' => 5000,
            'type' => 'one_time',
            'active' => true,
        ]);

        $this->assertNotNull($price);
        $this->assertEquals('one_time', $price->interval);
        $this->assertEquals('One-time', $price->label);
    }
}
