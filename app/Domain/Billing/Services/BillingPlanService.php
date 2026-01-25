<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\PriceProviderMapping;
use App\Domain\Billing\Models\Product as CatalogProduct;
use RuntimeException;

class BillingPlanService
{
    public function providers(): array
    {
        $providers = array_map('strtolower', config('saas.billing.providers', []));

        return array_values(array_unique($providers));
    }

    public function defaultProvider(): string
    {
        $provider = strtolower((string) config('saas.billing.default_provider', 'stripe'));

        return $provider ?: 'stripe';
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function plans(): array
    {
        if ($this->useDatabaseCatalog()) {
            return $this->plansFromDatabase();
        }

        return $this->plansFromConfig();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function plansForProvider(string $provider): array
    {
        $provider = strtolower($provider);
        $plans = $this->plans();

        foreach ($plans as &$plan) {
            $prices = [];

            foreach ($plan['prices'] as $priceKey => $price) {
                $price['key'] = $priceKey;
                $price['provider_id'] = $this->resolveProviderId($price, $provider);
                $price['amount'] = $this->resolveProviderValue($price, $provider, 'amounts', 'amount');
                $price['currency'] = $this->resolveProviderValue($price, $provider, 'currencies', 'currency');
                $price['is_available'] = ! empty($price['provider_id']);
                $prices[$priceKey] = $price;
            }

            $plan['prices'] = $prices;
            $plan['is_available'] = (bool) collect($prices)->first(fn (array $price): bool => $price['is_available']);
        }

        return $plans;
    }

    /**
     * @return array<string, mixed>
     */
    public function plan(string $planKey): array
    {
        if ($this->useDatabaseCatalog()) {
            $plan = $this->planFromDatabase($planKey);

            if ($plan) {
                return $plan;
            }
        }

        return $this->planFromConfig($planKey);
    }

    /**
     * @return array<string, mixed>
     */
    public function price(string $planKey, string $priceKey): array
    {
        $plan = $this->plan($planKey);

        if (! isset($plan['prices'][$priceKey])) {
            throw new RuntimeException("Unknown price [{$priceKey}] for plan [{$planKey}].");
        }

        $price = $plan['prices'][$priceKey];
        $price['key'] = $priceKey;

        return $price;
    }

    public function providerPriceId(string $provider, string $planKey, string $priceKey): ?string
    {
        if ($this->useDatabaseCatalog()) {
            $providerId = PriceProviderMapping::query()
                ->where('provider', strtolower($provider))
                ->whereHas('price', function ($query) use ($planKey, $priceKey) {
                    $query->whereHas('product', fn ($q) => $q->where('key', $planKey))
                        ->where(function ($q) use ($priceKey) {
                            $q->where('key', $priceKey)
                                ->orWhere('interval', $priceKey);
                        });
                })
                ->value('provider_id');

            if ($providerId) {
                return $providerId;
            }
        }

        $price = $this->price($planKey, $priceKey);

        return $this->resolveProviderId($price, $provider);
    }

    public function resolvePlanKeyByProviderId(string $provider, string $providerPriceId): ?string
    {
        $provider = strtolower($provider);

        if ($this->useDatabaseCatalog()) {
            $planKey = PriceProviderMapping::query()
                ->where('provider', $provider)
                ->where('provider_id', $providerPriceId)
                ->with('price.product')
                ->first()
                ?->price
                ?->product
                ?->key;

            if ($planKey) {
                return (string) $planKey;
            }
        }

        foreach (config('saas.billing.plans', []) as $planKey => $plan) {
            foreach (($plan['prices'] ?? []) as $price) {
                $resolved = $this->resolveProviderId($price, $provider);

                if ($resolved && $resolved === $providerPriceId) {
                    return (string) $planKey;
                }
            }
        }

        return null;
    }

    private function useDatabaseCatalog(): bool
    {
        $catalog = strtolower((string) config('saas.billing.catalog', 'database'));

        if ($catalog === 'config') {
            return false;
        }

        return CatalogProduct::query()->exists();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function plansFromConfig(): array
    {
        $plans = config('saas.billing.plans', []);
        $normalized = [];

        foreach ($plans as $key => $plan) {
            $plan['key'] = $key;
            $plan['type'] = $plan['type'] ?? 'subscription';
            $plan['prices'] = $plan['prices'] ?? [];

            $normalized[] = $plan;
        }

        return $normalized;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function plansFromDatabase(): array
    {
        return CatalogProduct::query()
            ->with(['prices.mappings'])
            ->where('is_active', true)
            ->whereHas('prices', function ($query) {
                $query->where('is_active', true);
            })
            ->orderBy('id')
            ->get()
            ->map(fn (CatalogProduct $plan): array => $this->normalizeDatabasePlan($plan))
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>|null
     */
    private function planFromDatabase(string $planKey): ?array
    {
        $plan = CatalogProduct::query()
            ->with(['prices.mappings'])
            ->where('key', $planKey)
            ->first();

        if (! $plan) {
            return null;
        }

        return $this->normalizeDatabasePlan($plan);
    }

    /**
     * @return array<string, mixed>
     */
    private function planFromConfig(string $planKey): array
    {
        $plans = config('saas.billing.plans', []);

        if (! isset($plans[$planKey])) {
            throw new RuntimeException("Unknown plan [{$planKey}].");
        }

        $plan = $plans[$planKey];
        $plan['key'] = $planKey;
        $plan['type'] = $plan['type'] ?? 'subscription';
        $plan['prices'] = $plan['prices'] ?? [];

        return $plan;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeDatabasePlan(CatalogProduct $plan): array
    {
        $prices = [];

        foreach ($plan->prices->where('is_active', true) as $price) {
            $priceKey = $price->key ?: $price->interval;

            if (! $priceKey) {
                continue;
            }

            if (! isset($prices[$priceKey])) {
                $prices[$priceKey] = [
                    'key' => $priceKey,
                    'label' => $price->label ?: ucfirst($priceKey),
                    'interval' => $price->interval,
                    'interval_count' => $price->interval_count,
                    'type' => $price->type,
                    'has_trial' => $price->has_trial,
                    'trial_interval' => $price->trial_interval,
                    'trial_interval_count' => $price->trial_interval_count,
                    'amount_is_minor' => true,
                    'providers' => [],
                    'amounts' => [],
                    'currencies' => [],
                ];
            }

            $prices[$priceKey]['providers'] = $price->mappings->pluck('provider_id', 'provider')->toArray();

            // For now, assume amounts/currencies are same across providers or fetched from generic price
            // If we wanted per-provider amounts, we'd need to store them in the mapping or fetch them.
            // But 'Price' model is now the single source of truth for amount/currency.
            foreach ($prices[$priceKey]['providers'] as $prov => $id) {
                $prices[$priceKey]['amounts'][$prov] = $price->amount;
                $prices[$priceKey]['currencies'][$prov] = $price->currency;
            }
            // Add self if no mappings? No, mappings are strictly for provider IDs.
        }

        return [
            'key' => $plan->key,
            'name' => $plan->name,
            'summary' => $plan->summary ?: $plan->description,
            'type' => $plan->type ?: 'subscription',
            'highlight' => (bool) $plan->is_featured,
            'entitlements' => $plan->entitlements ?? [],
            'features' => $plan->features ?? [],
            'prices' => $prices,
        ];
    }

    private function resolveProviderId(array $price, string $provider): ?string
    {
        $provider = strtolower($provider);

        if (isset($price['providers'][$provider])) {
            return $price['providers'][$provider];
        }

        if (isset($price['provider_ids'][$provider])) {
            return $price['provider_ids'][$provider];
        }

        if (isset($price['provider_id'])) {
            return $price['provider_id'];
        }

        return null;
    }

    private function resolveProviderValue(array $price, string $provider, string $key, string $fallbackKey): mixed
    {
        $provider = strtolower($provider);

        if (isset($price[$key][$provider])) {
            return $price[$key][$provider];
        }

        return $price[$fallbackKey] ?? null;
    }
}
