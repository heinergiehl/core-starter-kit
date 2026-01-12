<?php

namespace App\Domain\Billing\Exports;

use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;

interface CatalogPublishAdapter
{
    public function provider(): string;

    public function prepare(): void;

    /**
     * @return array{action: string, id: string|null}
     */
    public function ensureProduct(Product $product, bool $apply, bool $updateExisting): array;

    /**
     * @return array{action: string, id: string|null}
     */
    public function ensurePrice(Product $product, Price $price, string $providerProductId, bool $apply, bool $updateExisting): array;
}

