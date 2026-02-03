<?php

namespace App\Domain\Billing\Imports;

use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\PriceProviderMapping;
use App\Domain\Billing\Models\Product;
use App\Domain\Billing\Models\ProductProviderMapping;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CatalogImportService
{
    /**
     * @return array{summary: array<string, array<string, int>>, warnings: array<int, string>}
     */
    public function preview(string $provider, bool $updateExisting = false): array
    {
        return $this->sync($provider, false, $updateExisting);
    }

    /**
     * @return array{summary: array<string, array<string, int>>, warnings: array<int, string>}
     */
    public function apply(string $provider, bool $updateExisting = false): array
    {
        return $this->sync($provider, true, $updateExisting);
    }

    private function sync(string $provider, bool $apply, bool $updateExisting): array
    {
        $adapter = $this->adapter($provider);
        $payload = $adapter->fetch();
        $items = $payload['items'] ?? [];
        $warnings = $payload['warnings'] ?? [];

        $summary = [
            'products' => ['create' => 0, 'update' => 0, 'skip' => 0],
            'prices' => ['create' => 0, 'update' => 0, 'skip' => 0, 'skipped' => 0],
        ];

        $runner = function () use ($items, $provider, $apply, $updateExisting, &$summary): void {
            $productCache = [];

            foreach ($items as $item) {
                $productPayload = $item['product'] ?? [];
                $planPayload = $item['plan'] ?? [];
                if (! empty($planPayload)) {
                    $productPayload = array_merge($productPayload, $planPayload);
                    if (! empty($planPayload['key'])) {
                        $productPayload['key'] = $planPayload['key'];
                    }
                }
                $pricePayloads = $item['prices'] ?? [];

                if (empty($productPayload['key'])) {
                    $summary['products']['skip']++;

                    continue;
                }

                $product = $productCache[$productPayload['key']] ?? null;
                if (! $product) {
                    $product = Product::query()
                        ->where('key', $productPayload['key'])
                        ->first();
                }

                $productAction = $this->resolveAction($product, $productPayload, $updateExisting);
                $summary['products'][$productAction]++;

                if ($apply) {
                    $productAttributes = $productPayload;
                    unset($productAttributes['provider_id']);
                    
                    if ($productAction === 'create') {
                        $product = Product::create($productAttributes);
                    } elseif ($productAction === 'update' && $product) {
                        $product->update($this->filterUpdatablePayload($productAttributes));
                    }
                }

                if ($product) {
                    $productCache[$productPayload['key']] = $product;

                    // Sync mapping if provider_id is present
                    if ($apply && ! empty($productPayload['provider_id'])) {
                        ProductProviderMapping::updateOrCreate(
                            [
                                'provider' => $provider,
                                'provider_id' => $productPayload['provider_id'],
                            ],
                            [
                                'product_id' => $product->id,
                            ]
                        );
                    }
                }

                foreach ($pricePayloads as $pricePayload) {
                    if (empty($pricePayload['provider_id']) || ! array_key_exists('amount', $pricePayload) || $pricePayload['amount'] === null) {
                        $summary['prices']['skipped']++;

                        continue;
                    }

                    $providerId = (string) $pricePayload['provider_id'];
                    unset($pricePayload['provider'], $pricePayload['provider_id']);

                    $mapping = PriceProviderMapping::query()
                        ->where('provider', $provider)
                        ->where('provider_id', $providerId)
                        ->first();

                    // Note: We do NOT skip if mapping exists but has no price_id (tombstone).
                    // We want to re-link it if found/created.

                    $price = $mapping?->price;

                    $pricePayload['product_id'] = $product?->id ?? $price?->product_id;

                    $priceAction = $this->resolveAction($price, $pricePayload, $updateExisting);
                    $summary['prices'][$priceAction]++;

                    if ($apply) {
                        if ($priceAction === 'create') {
                            $price = Price::create($pricePayload);
                        } elseif ($priceAction === 'update' && $price) {
                            $price->update($this->filterUpdatablePayload($pricePayload));
                        }

                        if ($price) {
                            PriceProviderMapping::updateOrCreate(
                                [
                                    'provider' => $provider,
                                    'provider_id' => $providerId,
                                ],
                                [
                                    'price_id' => $price->id,
                                ]
                            );
                        }
                    }
                }
            }
        };

        if ($apply) {
            DB::transaction($runner);
        } else {
            $runner();
        }

        return [
            'summary' => $summary,
            'warnings' => $warnings,
        ];
    }

    private function adapter(string $provider): CatalogImportAdapter
    {
        $provider = strtolower($provider);

        return match ($provider) {
            \App\Enums\BillingProvider::Stripe->value => app(\App\Domain\Billing\Imports\StripeCatalogImportAdapter::class),
            \App\Enums\BillingProvider::Paddle->value => app(\App\Domain\Billing\Imports\PaddleCatalogImportAdapter::class),
            default => throw new RuntimeException("Catalog import provider [{$provider}] is not supported."),
        };
    }

    private function resolveAction(?object $model, array $payload, bool $updateExisting): string
    {
        if (! $model) {
            return 'create';
        }

        if (! $updateExisting) {
            return 'skip';
        }

        if ($this->hasChanges($model, $payload)) {
            return 'update';
        }

        return 'skip';
    }

    private function hasChanges(object $model, array $payload): bool
    {
        foreach ($payload as $key => $value) {
            $current = $model->{$key} ?? null;

            if (is_array($value)) {
                if ($value !== (array) $current) {
                    return true;
                }

                continue;
            }

            if ($value !== null && $value !== $current) {
                return true;
            }
        }

        return false;
    }

    private function filterUpdatablePayload(array $payload): array
    {
        return array_filter($payload, function ($value) {
            if (is_array($value)) {
                return $value !== [];
            }

            return $value !== null && $value !== '';
        });
    }
}
