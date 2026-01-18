<?php

use App\Domain\Billing\Models\Product;
use App\Jobs\BulkDeleteProductsJob;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

require __DIR__ . '/vendor/autoload.php';

$app = require __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

// 1. Setup
$team = \App\Domain\Organization\Models\Team::first();
if (!$team) die("No team found.\n");

$product = Product::create([
    'team_id' => $team->id,
    'key' => 'e2e_del_' . uniqid(),
    'name' => 'E2E Queue Test', 
    'type' => 'service',
    'is_active' => false,
]);

echo "Created Product {$product->id}\n";

// 2. Dispatch Async
echo "Dispatching Job to Queue...\n";
BulkDeleteProductsJob::dispatch([$product->id]);

// 3. Run Worker (Force processing of the job we just sent)
echo "Running Queue Worker...\n";
Artisan::call('queue:work', ['--once' => true]);
echo "Worker output:\n" . Artisan::output() . "\n";

// 4. Verify
$exists = Product::find($product->id);
if (!$exists) {
    echo "SUCCESS: Product was deleted via Queue.\n";
} else {
    echo "FAILURE: Product still exists.\n";
}
