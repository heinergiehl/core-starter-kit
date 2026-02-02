<?php

namespace App\Domain\Billing\Contracts;

use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;

interface BillingCatalogProvider
{
    /**
     * Create a product on the provider.
     *
     * @return string The provider's product ID
     */
    public function createProduct(Product $product): string;

    /**
     * Update a product on the provider.
     */
    public function updateProduct(Product $product, string $providerId): void;

    /**
     * Create a price on the provider.
     *
     * @return string The provider's price ID
     */
    public function createPrice(Price $price): string;

    /**
     * Update a price on the provider.
     * Note: Many providers (like Stripe) don't allow changing price amounts/intervals
     * once created, only metadata/active status.
     */
    public function updatePrice(Price $price, string $providerId): void;
}
