<?php

use App\Domain\Billing\Models\Product;

echo "--- Checking All Products ---\n";

$products = Product::with('providerMappings')->get();

foreach ($products as $p) {
    echo "Product [{$p->id}] '{$p->name}' ({$p->key}) - " . ($p->is_active ? 'ACTIVE' : 'INACTIVE') . "\n";
    foreach ($p->providerMappings as $m) {
        echo "  - {$m->provider}: {$m->provider_id}\n";
    }
}
ob_flush();
