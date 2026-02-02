<?php

namespace App\Domain\Billing\Adapters\Stripe;

use App\Domain\Billing\Contracts\BillingCatalogProvider;
use App\Domain\Billing\Exceptions\BillingException;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use App\Enums\BillingProvider;
use App\Enums\PriceType;
use Illuminate\Support\Str;
use Stripe\StripeClient;
use Exception;

class StripeProviderClient implements BillingCatalogProvider
{
    private StripeClient $client;

    public function __construct(array $config)
    {
        $this->client = new StripeClient($config['secret_key']);
    }

    public function createProduct(Product $product): string
    {
        try {
            $response = $this->client->products->create([
                'name' => $product->name,
                'description' => $product->description ?: null,
                'active' => $product->is_active,
                'metadata' => [
                    'saas_product_id' => $product->id,
                    'key' => $product->key,
                ],
            ]);

            return $response->id;
        } catch (Exception $e) {
            throw BillingException::failedAction(BillingProvider::Stripe, 'create product', $e->getMessage());
        }
    }

    public function updateProduct(Product $product, string $providerId): void
    {
        try {
            $this->client->products->update($providerId, [
                'name' => $product->name,
                'description' => $product->description ?: null,
                'active' => $product->is_active,
                'metadata' => [
                    'saas_product_id' => $product->id,
                    'key' => $product->key,
                ],
            ]);
        } catch (Exception $e) {
            throw BillingException::failedAction(BillingProvider::Stripe, 'update product', $e->getMessage());
        }
    }

    public function createPrice(Price $price): string
    {
        $data = [
            'unit_amount' => $price->amount, // Stripe expects integer cents
            'currency' => strtolower($price->currency),
            'product' => $price->product->providerMappings()->where('provider', BillingProvider::Stripe->value)->first()?->provider_id,
            'active' => $price->is_active,
            'metadata' => [
                'saas_price_id' => $price->id,
                'key' => $price->key,
            ],
        ];

        if ($price->type === PriceType::Recurring) {
            $data['recurring'] = [
                'interval' => $price->interval, // month, year, week, day
                'interval_count' => $price->interval_count,
            ];
            
            if ($price->has_trial && $price->trial_interval_count) {
                 // Stripe trial_period_days is on the PRICE (subscription schedule) or PRODUCT (legacy), 
                 // actually Stripe keeps trials on the Subscription object usually, 
                 // but Price API doesn't have 'trial_period_days' for standard recurrences easily without 'recurring[trial_period_days]' strictly.
                 // WARNING: Stripe Price API does NOT support creating a price with a built-in trial period directly in the core params easily for Checkout 
                 // unless using specific flows. However, for simple compatibility let's skip trial logic on the Price object 
                 // and assume the Checkout handler applies it from the Price model's definition.
            }
        }

        try {
            $stripePrice = $this->client->prices->create($data);
            return $stripePrice->id;
        } catch (Exception $e) {
            throw BillingException::failedAction(BillingProvider::Stripe, 'create price', $e->getMessage());
        }
    }

    public function updatePrice(Price $price, string $providerId): void
    {
        // Stripe prices are immutable regarding amount/currency/interval.
        // We can only update active status and metadata.
        try {
            $this->client->prices->update($providerId, [
                'active' => $price->is_active,
                'metadata' => [
                    'saas_price_id' => $price->id,
                    'key' => $price->key,
                ],
            ]);
        } catch (Exception $e) {
            throw BillingException::failedAction(BillingProvider::Stripe, 'update price', $e->getMessage());
        }
    }
}
