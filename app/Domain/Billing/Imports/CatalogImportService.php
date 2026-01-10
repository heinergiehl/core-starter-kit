<?php

namespace App\Domain\Billing\Imports;

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
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
            'plans' => ['create' => 0, 'update' => 0, 'skip' => 0],
            'prices' => ['create' => 0, 'update' => 0, 'skip' => 0, 'skipped' => 0],
        ];

        $runner = function () use ($items, $provider, $apply, $updateExisting, &$summary): void {
            $productCache = [];

            foreach ($items as $item) {
                $productPayload = $item['product'] ?? [];
                $planPayload = $item['plan'] ?? [];
                $pricePayloads = $item['prices'] ?? [];

                if (empty($productPayload['key']) || empty($planPayload['key'])) {
                    $summary['plans']['skip']++;
                    continue;
                }

                $product = $productCache[$productPayload['key']] ?? null;
                if (!$product) {
                    $product = Product::query()
                        ->where('key', $productPayload['key'])
                        ->first();
                }

                $productAction = $this->resolveAction($product, $productPayload, $updateExisting);
                $summary['products'][$productAction]++;

                if ($apply) {
                    if ($productAction === 'create') {
                        $product = Product::create($productPayload);
                    } elseif ($productAction === 'update' && $product) {
                        $product->update($this->filterUpdatablePayload($productPayload));
                    }
                }

                if ($product) {
                    $productCache[$productPayload['key']] = $product;
                }

                $plan = Plan::query()
                    ->where('key', $planPayload['key'])
                    ->first();

                $planPayload['product_id'] = $product?->id ?? $plan?->product_id;

                $planAction = $this->resolveAction($plan, $planPayload, $updateExisting);
                $summary['plans'][$planAction]++;

                if ($apply) {
                    if ($planAction === 'create') {
                        $plan = Plan::create($planPayload);
                    } elseif ($planAction === 'update' && $plan) {
                        $plan->update($this->filterUpdatablePayload($planPayload));
                    }
                }

                foreach ($pricePayloads as $pricePayload) {
                    if (empty($pricePayload['provider_id']) || !array_key_exists('amount', $pricePayload) || $pricePayload['amount'] === null) {
                        $summary['prices']['skipped']++;
                        continue;
                    }

                    $price = Price::query()
                        ->where('provider', $provider)
                        ->where('provider_id', $pricePayload['provider_id'])
                        ->first();

                    $pricePayload['plan_id'] = $plan?->id ?? $price?->plan_id;

                    $priceAction = $this->resolveAction($price, $pricePayload, $updateExisting);
                    $summary['prices'][$priceAction]++;

                    if ($apply) {
                        if ($priceAction === 'create') {
                            Price::create($pricePayload);
                        } elseif ($priceAction === 'update' && $price) {
                            $price->update($this->filterUpdatablePayload($pricePayload));
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
            'stripe' => app(StripeCatalogImportAdapter::class),
            default => throw new RuntimeException("Catalog import provider [{$provider}] is not supported."),
        };
    }

    private function resolveAction(?object $model, array $payload, bool $updateExisting): string
    {
        if (!$model) {
            return 'create';
        }

        if (!$updateExisting) {
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
