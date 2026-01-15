<?php

use App\Domain\Billing\Models\Price;

// Load Laravel
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Debugging Prices:\n";
$prices = Price::where('is_active', true)->with(['product', 'mappings'])->get();

foreach($prices as $p) {
    echo "Product: " . ($p->product->name ?? 'N/A') . " (Key: " . ($p->product->key ?? 'N/A') . ")\n";
    echo "  Price Key: " . $p->key . "\n";
    echo "  Amount: " . $p->amount . "\n";
    echo "  Mappings:\n";
    foreach($p->mappings as $m) {
        echo "    - " . $m->provider . ": " . $m->provider_id . "\n";
    }
    echo "-------------------\n";
}

echo "\nAll Mappings:\n";
$mappings = \App\Domain\Billing\Models\PriceProviderMapping::all();
foreach($mappings as $m) {
    echo "ID: " . $m->id . " | PriceID: " . $m->price_id . " | Provider: " . $m->provider . " | PID: " . $m->provider_id . "\n";
}
