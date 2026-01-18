<?php

use Illuminate\Support\Facades\Http;

$apiKey = config('services.paddle.api_key');
$environment = config('services.paddle.environment', 'production');
$baseUrl = $environment === 'sandbox' ? 'https://sandbox-api.paddle.com' : 'https://api.paddle.com';

echo "Environment: $environment\n";
echo "Base URL: $baseUrl\n";

$url = "{$baseUrl}/products?per_page=100&status=active,archived";

echo "Fetching: $url\n";

$response = Http::withToken($apiKey)->get($url);

if ($response->successful()) {
    $data = $response->json();
    $meta = $data['meta'] ?? [];
    $count = count($data['data'] ?? []);
    
    echo "Success!\n";
    echo "Items on this page: $count\n";
    echo "Pagination Metadata:\n";
    print_r($meta);
} else {
    echo "Failed: " . $response->status() . "\n";
    echo $response->body() . "\n";
}
