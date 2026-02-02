<?php

use App\Domain\Billing\Models\PaymentProvider;
use Illuminate\Support\Facades\DB;

// Load app
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$providers = PaymentProvider::all();
echo "Total Providers: " . $providers->count() . "\n";
foreach ($providers as $p) {
    echo "Provider: {$p->slug} | Active: " . ($p->is_active ? 'YES' : 'NO') . "\n";
}

$mappingsCount = DB::table('billing_price_provider_mappings')->count();
echo "Total Price Mappings: " . $mappingsCount . "\n";
