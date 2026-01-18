<?php

use Illuminate\Support\Facades\Http;

echo "--- Checking Paddle ID pro_01kez40z9as9gdj5ccxsre683j ---\n";

$apiKey = config('services.paddle.api_key');
$isSandbox = config('services.paddle.environment') === 'sandbox';
$baseUrl = $isSandbox ? 'https://sandbox-api.paddle.com' : 'https://api.paddle.com';

$response = Http::withToken($apiKey)->get("{$baseUrl}/products/pro_01key5cr4mrx9kh1pbcwryzxys");

if ($response->successful()) {
    $data = $response->json('data');
    echo "ID: {$data['id']}\n";
    echo "Name: {$data['name']}\n";
    echo "Status: {$data['status']}\n";
} else {
    echo "Error: " . $response->body() . "\n";
}
