<?php

namespace App\Domain\Billing\Exports;

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Exports\StripeCatalogPublishAdapter;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class CatalogPublishService
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

    /**
     * @return array{summary: array<string, array<string, int>>, warnings: array<int, string>}
     */
    private function sync(string $provider, bool $apply, bool $updateExisting): array
    {
        $provider = strtolower($provider);
        $enabled = array_map('strtolower', config('saas.billing.providers', []));

        if (!in_array($provider, $enabled, true)) {
            throw new RuntimeException("Catalog publish provider [{$provider}] is not enabled.");
        }

        $adapter = $this->adapter($provider);
        $adapter->prepare();

        $summary = [
            'products' => ['create' => 0, 'update' => 0, 'skip' => 0],
            'prices' => ['create' => 0, 'update' => 0, 'skip' => 0, 'link' => 0],
        ];
        $warnings = [];

        $runner = function () use ($provider, $adapter, $apply, $updateExisting, &$summary, &$warnings): void {
            $plans = Plan::query()
                ->with([
                    'product',
                    'prices' => fn ($query) => $query->where('provider', $provider),
                ])
                ->orderBy('id')
                ->get();

            foreach ($plans as $plan) {
                $product = $plan->product;
                if (!$product) {
                    $warnings[] = "Plan [{$plan->key}] is missing a product record.";
                    $summary['products']['skip']++;
                    continue;
                }

                $productResult = $adapter->ensureProduct($product, $plan, $apply, $updateExisting);
                $productAction = $productResult['action'] ?? 'skip';
                if (isset($summary['products'][$productAction])) {
                    $summary['products'][$productAction]++;
                }

                $providerProductId = $productResult['id'] ?? null;
                if (!$providerProductId) {
                    $warnings[] = "Plan [{$plan->key}] could not resolve a {$provider} product id.";
                    continue;
                }

                if ($plan->prices->isEmpty()) {
                    $warnings[] = "Plan [{$plan->key}] has no {$provider} prices.";
                    continue;
                }

                foreach ($plan->prices as $price) {
                    $priceResult = $adapter->ensurePrice($plan, $price, $providerProductId, $apply, $updateExisting);
                    $priceAction = $priceResult['action'] ?? 'skip';
                    if (isset($summary['prices'][$priceAction])) {
                        $summary['prices'][$priceAction]++;
                    }

                    if ($apply && !empty($priceResult['id']) && $price->provider_id !== $priceResult['id']) {
                        $price->update(['provider_id' => $priceResult['id']]);
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

    private function adapter(string $provider): CatalogPublishAdapter
    {
        return match ($provider) {
            'stripe' => app(StripeCatalogPublishAdapter::class),
            default => throw new RuntimeException("Catalog publish provider [{$provider}] is not supported."),
        };
    }
}
