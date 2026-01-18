<?php

use Illuminate\Support\Facades\DB;

echo "=== Database Performance Test ===\n";

$start = microtime(true);
for ($i = 0; $i < 10; $i++) {
    DB::table('products')->count();
}
$elapsed = round((microtime(true) - $start) * 1000);
echo "10 simple COUNT queries: {$elapsed}ms\n";

$start = microtime(true);
for ($i = 0; $i < 10; $i++) {
    DB::table('products')->where('key', 'LIKE', 'test-%')->get();
}
$elapsed = round((microtime(true) - $start) * 1000);
echo "10 LIKE queries: {$elapsed}ms\n";

$start = microtime(true);
for ($i = 0; $i < 10; $i++) {
    App\Domain\Billing\Models\Product::whereDoesntHave('providerMappings', function($q) {
        $q->where('provider', 'paddle')->where('provider_id', 'test');
    })->count();
}
$elapsed = round((microtime(true) - $start) * 1000);
echo "10 subquery (whereDoesntHave) queries: {$elapsed}ms\n";
