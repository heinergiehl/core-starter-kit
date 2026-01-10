<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Adapters\Stripe\Handlers\StripePriceHandler;
use App\Domain\Billing\Models\Plan;
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
        $this->handler = new StripePriceHandler();
    }

    public function test_handles_price_created_event(): void
    {
        // Create product and plan first
        $product = Product::create([
            'key' => 'test-product-abc123',
            'name' => 'Test Product',
            'provider' => 'stripe',
            'provider_id' => 'prod_PriceTest123',
            'is_active' => true,
        ]);

        Plan::create([
            'product_id' => $product->id,
            'key' => 'test-plan',
            'name' => 'Test Plan',
            'type' => 'subscription',
            'provider' => 'stripe',
            'provider_id' => 'prod_PriceTest123',
            'is_active' => true,
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
            'provider' => 'stripe',
            'provider_id' => 'price_TestPrice123',
            'currency' => 'USD',
            'amount' => 2900,
            'interval' => 'month',
            'interval_count' => 1,
            'is_active' => true,
        ]);
    }

    public function test_handles_price_deleted_event(): void
    {
        // Create product, plan, and price
        $product = Product::create([
            'key' => 'test-product-del123',
            'name' => 'Test Product',
            'provider' => 'stripe',
            'provider_id' => 'prod_DeletePrice',
            'is_active' => true,
        ]);

        $plan = Plan::create([
            'product_id' => $product->id,
            'key' => 'test-plan-del',
            'name' => 'Test Plan',
            'type' => 'subscription',
            'provider' => 'stripe',
            'provider_id' => 'prod_DeletePrice',
            'is_active' => true,
        ]);

        Price::create([
            'plan_id' => $plan->id,
            'key' => 'monthly-del123',
            'provider' => 'stripe',
            'provider_id' => 'price_ToDelete123',
            'interval' => 'month',
            'currency' => 'USD',
            'amount' => 1000,
            'is_active' => true,
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
            'provider' => 'stripe',
            'provider_id' => 'price_ToDelete123',
            'is_active' => false,
        ]);
    }

    public function test_generates_human_readable_label(): void
    {
        $product = Product::create([
            'key' => 'label-test-abc123',
            'name' => 'Label Test',
            'provider' => 'stripe',
            'provider_id' => 'prod_LabelTest123',
            'is_active' => true,
        ]);

        Plan::create([
            'product_id' => $product->id,
            'key' => 'label-test-plan',
            'name' => 'Label Test Plan',
            'type' => 'subscription',
            'provider' => 'stripe',
            'provider_id' => 'prod_LabelTest123',
            'is_active' => true,
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
            'provider' => 'stripe',
            'provider_id' => 'prod_OneTime123',
            'is_active' => true,
        ]);

        Plan::create([
            'product_id' => $product->id,
            'key' => 'onetime-test-plan',
            'name' => 'One Time Plan',
            'type' => 'one_time',
            'provider' => 'stripe',
            'provider_id' => 'prod_OneTime123',
            'is_active' => true,
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
