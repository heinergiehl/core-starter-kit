<?php

use App\Models\User;
use App\Domain\Billing\Models\Subscription;
use App\Enums\SubscriptionStatus;
use App\Enums\BillingProvider;
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';

$app->make(Kernel::class)->bootstrap();

echo "Booted.\n";

try {
    $user = User::factory()->create();
    echo "User created: {$user->id}\n";

    echo "Creating subscription...\n";
    $sub = Subscription::create([
        'user_id' => $user->id,
        'status' => SubscriptionStatus::Canceled,
        'plan_key' => 'pro-monthly',
        'ends_at' => now()->addDays(5),
        'provider' => BillingProvider::Stripe,
        'provider_id' => 'sub_123',
        'quantity' => 1,
    ]);
    echo "Subscription created: {$sub->id}\n";
    
    echo "Calling activeSubscription()...\n";
    $active = $user->activeSubscription();
    
    if ($active) {
        echo "Active subscription found: {$active->id}\n";
    } else {
        echo "No active subscription found (UNEXPECTED)\n";
    }

} catch (\Throwable $e) {
    echo "Caught exception: " . get_class($e) . "\n";
    echo "Message: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}
