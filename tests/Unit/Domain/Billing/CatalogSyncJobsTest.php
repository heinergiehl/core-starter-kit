<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Contracts\BillingCatalogProvider;
use App\Domain\Billing\Jobs\SyncPriceToProviders;
use App\Domain\Billing\Jobs\SyncProductToProviders;
use App\Domain\Billing\Models\PaymentProvider;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use App\Domain\Billing\Models\ProductProviderMapping;
use App\Domain\Billing\Services\BillingProviderManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class CatalogSyncJobsTest extends TestCase
{
    use RefreshDatabase;

    public function test_product_sync_job_runs_after_commit(): void
    {
        $product = Product::withoutEvents(fn () => Product::factory()->create());

        $this->assertTrue((new SyncProductToProviders($product))->afterCommit);
    }

    public function test_price_sync_job_runs_after_commit(): void
    {
        $product = Product::withoutEvents(fn () => Product::factory()->create());
        $price = Price::withoutEvents(fn () => Price::factory()->for($product)->create());

        $this->assertTrue((new SyncPriceToProviders($price))->afterCommit);
    }

    public function test_product_sync_job_throws_when_any_provider_sync_fails(): void
    {
        $product = Product::withoutEvents(fn () => Product::factory()->create());

        PaymentProvider::create(['name' => 'Stripe', 'slug' => 'stripe', 'is_active' => true]);
        PaymentProvider::create(['name' => 'Paddle', 'slug' => 'paddle', 'is_active' => true]);

        $stripe = Mockery::mock(BillingCatalogProvider::class);
        $stripe->shouldReceive('createProduct')->once()->with($product)->andReturn('prod_stripe');

        $paddle = Mockery::mock(BillingCatalogProvider::class);
        $paddle->shouldReceive('createProduct')->once()->with($product)->andThrow(new RuntimeException('Paddle unavailable'));

        $manager = Mockery::mock(BillingProviderManager::class);
        $manager->shouldReceive('catalog')->once()->with('stripe')->andReturn($stripe);
        $manager->shouldReceive('catalog')->once()->with('paddle')->andReturn($paddle);

        $job = new SyncProductToProviders($product, false);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('paddle');

        try {
            $job->handle($manager);
        } finally {
            $this->assertDatabaseHas('product_provider_mappings', [
                'product_id' => $product->id,
                'provider' => 'stripe',
                'provider_id' => 'prod_stripe',
            ]);
        }
    }

    public function test_price_sync_job_throws_when_provider_sync_fails(): void
    {
        $product = Product::withoutEvents(fn () => Product::factory()->create());
        $price = Price::withoutEvents(fn () => Price::factory()->for($product)->create());

        PaymentProvider::create(['name' => 'Stripe', 'slug' => 'stripe', 'is_active' => true]);

        ProductProviderMapping::create([
            'product_id' => $product->id,
            'provider' => 'stripe',
            'provider_id' => 'prod_stripe',
        ]);

        $provider = Mockery::mock(BillingCatalogProvider::class);
        $provider->shouldReceive('createPrice')->once()->with($price)->andThrow(new RuntimeException('Stripe rejected price'));

        $manager = Mockery::mock(BillingProviderManager::class);
        $manager->shouldReceive('catalog')->once()->with('stripe')->andReturn($provider);

        $job = new SyncPriceToProviders($price);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('stripe');

        $job->handle($manager);
    }
}
