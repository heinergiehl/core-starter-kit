<?php

use App\Domain\Billing\Models\Product;

echo "--- Active Products Diagnosis ---\n";

$products = Product::where('is_active', true)->with('providerMappings')->get();

foreach ($products as $p) {
    echo "Product [{$p->id}] '{$p->name}'\n";
    if ($p->providerMappings->isEmpty()) {
        echo "  [!] NO MAPPINGS FOUND (Ghost?)\n";
    } else {
        echo "  [OK] Mapped to:\n";
        foreach ($p->providerMappings as $m) {
            echo "    -> {$m->provider}: {$m->provider_id}\n";
        }
    }
    echo "--------------------------\n";
}
