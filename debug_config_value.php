<?php
try {
    $provider = \App\Domain\Billing\Models\PaymentProvider::where('slug', 'stripe')->first();
    
    if ($provider) {
        $config = $provider->configuration ?? [];
        // Force manual decrypt attempt if needed, but model cast handles it.
        
        echo "Raw Config Dump:\n";
        print_r($config);

        $merged = array_merge($config, $provider->connection_settings ?? []);

        echo "\nWebhook Secret Value check:\n";
        if (isset($merged['webhook_secret'])) {
            echo "Key exists.\n";
            echo "Value length: " . strlen($merged['webhook_secret']) . "\n";
            echo "Value start: " . substr($merged['webhook_secret'], 0, 5) . "...\n";
        } else {
            echo "Key MISSING in merged array.\n";
        }
    }

} catch (\Throwable $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
