<?php

use App\Domain\Billing\Models\Product;

echo "--- Checking for Multi-Mapped Products ---\n";

$products = Product::has('providerMappings', '>', 1)->with('providerMappings')->get();

if ($products->isEmpty()) {
    echo "No products with multiple mappings found.\n";
} else {
    foreach ($products as $p) {
        echo "Product [{$p->id}] '{$p->name}' has " . $p->providerMappings->count() . " mappings:\n";
        foreach ($p->providerMappings as $m) {
            echo "  - {$m->provider}: {$m->provider_id}\n";
        }
        echo "--------------------------\n";
    }
}
