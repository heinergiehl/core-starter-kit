<?php

namespace App\Domain\Billing\Adapters\Paddle;

use App\Domain\Billing\Contracts\BillingCatalogProvider;
use App\Domain\Billing\Exceptions\BillingException;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use App\Enums\BillingProvider;
use App\Enums\PriceType;
use Illuminate\Support\Facades\Http;

class PaddleProviderClient implements BillingCatalogProvider
{
    private string $baseUrl;

    private string $apiKey;

    public function __construct(array $config)
    {
        $environment = $config['environment'] ?? 'production';
        $this->baseUrl = $environment === 'sandbox' ? 'https://sandbox-api.paddle.com' : 'https://api.paddle.com';
        $apiKey = trim((string) ($config['api_key'] ?? ''));

        if ($apiKey === '') {
            throw BillingException::missingConfiguration(BillingProvider::Paddle, 'api_key');
        }

        $this->apiKey = $apiKey;
    }

    public function createProduct(Product $product): string
    {
        $response = Http::withToken($this->apiKey)->post("{$this->baseUrl}/products", [
            'name' => $product->name,
            'description' => $product->description,
            'tax_category' => 'standard', // Required by Paddle
            'custom_data' => [
                'plan_key' => (string) $product->key,
                'saas_product_id' => (string) $product->id,
            ],
        ]);

        if (! $response->successful()) {
            throw BillingException::failedAction(BillingProvider::Paddle, 'create product', $response->body());
        }

        return $response->json('data.id');
    }

    public function updateProduct(Product $product, string $providerId): void
    {
        $response = Http::withToken($this->apiKey)->patch("{$this->baseUrl}/products/{$providerId}", [
            'name' => $product->name,
            'description' => $product->description,
            'tax_category' => 'standard', // Required by Paddle
            'status' => $product->is_active ? 'active' : 'archived',
            'custom_data' => [
                'plan_key' => $product->key,
                'saas_product_id' => $product->id,
            ],
        ]);

        if (! $response->successful()) {
            throw BillingException::failedAction(BillingProvider::Paddle, 'update product', $response->body());
        }
    }

    public function createPrice(Price $price): string
    {
        $productId = $price->product->providerMappings()->where('provider', BillingProvider::Paddle->value)->first()?->provider_id;

        if (! $productId) {
            throw BillingException::failedAction(BillingProvider::Paddle, 'create price', "Product ID not found for Price {$price->id}");
        }

        $data = [
            'product_id' => $productId,
            'description' => $price->label ?? $price->key,
            'unit_price' => [
                'amount' => (string) $price->amount, // Paddle treats amount as string usually, but int is fine? API docs say object.
                'currency_code' => strtoupper($price->currency),
            ],
            'custom_data' => [
                'price_key' => $price->key,
                'saas_price_id' => $price->id,
            ],
        ];

        if ($price->type === PriceType::Recurring) {
            $data['billing_cycle'] = [
                'interval' => $price->interval,
                'frequency' => $price->interval_count,
            ];

            // Paddle handles trial periods on prices
            if ($price->has_trial && $price->trial_interval_count) {
                $data['trial_period'] = [
                    'interval' => $price->trial_interval ?? 'day',
                    'frequency' => $price->trial_interval_count,
                ];
            }
        }

        $response = Http::withToken($this->apiKey)->post("{$this->baseUrl}/prices", $data);

        if (! $response->successful()) {
            throw BillingException::failedAction(BillingProvider::Paddle, 'create price', $response->body());
        }

        return $response->json('data.id');
    }

    public function updatePrice(Price $price, string $providerId): void
    {
        // Paddle prices are also largely immutable. We can update description and active status.
        // Note: Paddle doesn't have a direct 'active' status on Price IIRC, it uses 'status' = 'active' | 'archived'

        $response = Http::withToken($this->apiKey)->patch("{$this->baseUrl}/prices/{$providerId}", [
            'description' => $price->label ?? $price->key,
            'status' => $price->is_active ? 'active' : 'archived',
            'custom_data' => [
                'price_key' => $price->key,
                'saas_price_id' => $price->id,
            ],
        ]);

        if (! $response->successful()) {
            throw BillingException::failedAction(BillingProvider::Paddle, 'update price', $response->body());
        }
    }
}
