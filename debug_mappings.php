<?php

use App\Domain\Billing\Models\PriceProviderMapping;
use Illuminate\Support\Facades\DB;

// Load app
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$mappings = PriceProviderMapping::with('price.product')->get();

echo "Price Mappings:\n";
foreach ($mappings as $m) {
    echo sprintf(
        "[%s] %s (%s) -> %s\n", 
        $m->provider, 
        $m->price->product->key ?? 'N/A', 
        $m->price->key ?? 'N/A', 
        $m->provider_id
    );
}
