<?php

use App\Domain\Billing\Models\Product;

echo "--- Debugging Mappings ---\n";

$products = Product::where('name', 'like', '%lemon%')->with('providerMappings')->get();

foreach ($products as $p) {
    echo "Product [{$p->id}] '{$p->name}'\n";
    echo "  Status: " . ($p->is_active ? 'ACTIVE' : 'INACTIVE') . "\n";
    echo "  Mappings: " . $p->providerMappings->count() . "\n";
    
    foreach ($p->providerMappings as $m) {
        echo "    - Provider: {$m->provider} | ID: {$m->provider_id}\n";
    }
    echo "--------------------------\n";
}
