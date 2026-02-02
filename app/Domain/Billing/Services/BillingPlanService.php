<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Data\Plan;
use App\Domain\Billing\Data\Price;
use App\Domain\Billing\Models\PriceProviderMapping;
use App\Domain\Billing\Models\Product as CatalogProduct;
use App\Enums\BillingProvider;
use Illuminate\Support\Collection;
use RuntimeException;

class BillingPlanService
{
    /**
     * @return array<int, string>
     */
    public function providers(): array
    {
        return \App\Domain\Billing\Models\PaymentProvider::where('is_active', true)
            ->pluck('slug')
            ->map(fn ($slug) => strtolower($slug))
            ->toArray();
    }

    public function defaultProvider(): string
    {
        $default = strtolower((string) config('saas.billing.default_provider', BillingProvider::Stripe->value));
        $activeProviders = $this->providers();

        if (in_array($default, $activeProviders, true)) {
            return $default;
        }

        return $activeProviders[0] ?? BillingProvider::Stripe->value;
    }

    /**
     * @return Collection<int, Plan>
     */
    public function plans(): Collection
    {
        return $this->plansFromDatabase();
    }

    /**
     * @return Collection<int, Plan>
     */
    public function plansForProvider(BillingProvider|string $provider): Collection
    {
        $provider = $this->normalizeProvider($provider);

        return $this->plans()->map(function (Plan $plan) use ($provider) {
            $prices = array_map(function (Price $price) use ($provider) {
                $providerId = $this->resolveProviderId($price, $provider);
                
                return new Price(
                    key: $price->key,
                    label: $price->label,
                    amount: $this->resolveProviderValue($price, $provider, 'amounts', 'amount'),
                    currency: $this->resolveProviderValue($price, $provider, 'currencies', 'currency'),
                    interval: $price->interval,
                    intervalCount: $price->intervalCount,
                    type: $price->type,
                    hasTrial: $price->hasTrial,
                    trialInterval: $price->trialInterval,
                    trialIntervalCount: $price->trialIntervalCount,
                    providerIds: $price->providerIds,
                    providerAmounts: $price->providerAmounts,
                    providerCurrencies: $price->providerCurrencies,
                    contextProviderId: $providerId,
                    isAvailable: ! empty($providerId),
                    amountIsMinor: $price->amountIsMinor,
                );
            }, $plan->prices);

            $isAvailable = (bool) collect($prices)->first(fn (Price $price) => $price->isAvailable);

            return new Plan(
                key: $plan->key,
                name: $plan->name,
                summary: $plan->summary,
                type: $plan->type,
                highlight: $plan->highlight,
                features: $plan->features,
                entitlements: $plan->entitlements,
                prices: $prices,
                isAvailable: $isAvailable,
            );
        });
    }

    public function plan(string $planKey): Plan
    {
        $plan = $this->planFromDatabase($planKey);

        if ($plan) {
            return $plan;
        }

        throw new RuntimeException("Unknown plan [{$planKey}].");
    }

    public function price(string $planKey, string $priceKey): Price
    {
        $plan = $this->plan($planKey);
        $price = $plan->getPrice($priceKey);

        if (! $price) {
            throw new RuntimeException("Unknown price [{$priceKey}] for plan [{$planKey}].");
        }

        return $price;
    }

    public function providerPriceId(BillingProvider|string $provider, string $planKey, string $priceKey): ?string
    {
        $provider = $this->normalizeProvider($provider);

        $providerId = PriceProviderMapping::query()
            ->where('provider', $provider)
            ->whereHas('price', function ($query) use ($planKey, $priceKey) {
                $query->whereHas('product', fn ($q) => $q->where('key', $planKey))
                    ->where(function ($q) use ($priceKey) {
                        $q->where('key', $priceKey)
                            ->orWhere('interval', $priceKey);
                    });
            })
            ->value('provider_id');

        return $providerId ?: null;
    }

    public function resolvePlanKeyByProviderId(BillingProvider|string $provider, string $providerPriceId): ?string
    {
        $provider = $this->normalizeProvider($provider);

        $planKey = PriceProviderMapping::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerPriceId)
            ->with('price.product')
            ->first()
            ?->price
            ?->product
            ?->key;

        return $planKey ? (string) $planKey : null;
    }


    /**
     * @return Collection<int, Plan>
     */
    private function plansFromDatabase(): Collection
    {
        $shownPlans = config('saas.billing.pricing.shown_plans', []);

        $query = CatalogProduct::query()
            ->with(['prices.mappings'])
            ->where('is_active', true)
            ->whereHas('prices', function ($query) {
                $query->where('is_active', true);
            });

        if (! empty($shownPlans)) {
            $query->whereIn('key', $shownPlans);
        }

        $plans = $query->get()
            ->map(fn (CatalogProduct $plan): Plan => $this->normalizeDatabasePlan($plan));

        if (! empty($shownPlans)) {
            // Sort according to the config order
            $shownPlansMap = array_flip($shownPlans);
            return $plans->sortBy(fn (Plan $plan) => $shownPlansMap[$plan->key] ?? 999)
                ->values();
        }

        return $plans->sortBy('id')->values();
    }

    private function planFromDatabase(string $planKey): ?Plan
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

    private function normalizeDatabasePlan(CatalogProduct $plan): Plan
    {
        $prices = [];

        foreach ($plan->prices->where('is_active', true) as $priceModel) {
            $priceKey = $priceModel->key ?: $priceModel->interval;

            if (! $priceKey) {
                continue;
            }

            if (isset($prices[$priceKey])) {
                continue;
            }

            $providerIds = $priceModel->mappings->pluck('provider_id', 'provider')->toArray();
            
            $amounts = [];
            $currencies = [];
            foreach ($providerIds as $prov => $id) {
                $amounts[$prov] = $priceModel->amount;
                $currencies[$prov] = $priceModel->currency;
            }

            $prices[$priceKey] = new Price(
                key: $priceKey,
                label: $priceModel->label ?: ucfirst($priceKey),
                amount: $priceModel->amount,
                currency: $priceModel->currency,
                interval: $priceModel->interval ?? 'month',
                intervalCount: $priceModel->interval_count ?? 1,
                type: $priceModel->type instanceof \BackedEnum ? $priceModel->type : ($priceModel->type ?? \App\Enums\PriceType::Recurring),
                hasTrial: (bool) $priceModel->has_trial,
                trialInterval: $priceModel->trial_interval,
                trialIntervalCount: $priceModel->trial_interval_count,
                providerIds: $providerIds,
                providerAmounts: $amounts,
                providerCurrencies: $currencies,
                contextProviderId: null,
                isAvailable: true,
                amountIsMinor: true, // DB amounts are minor units
            );
        }

        return new Plan(
            key: $plan->key,
            name: $plan->name,
            summary: $plan->summary ?: $plan->description ?: '',
            type: ($plan->type instanceof \BackedEnum ? $plan->type : \App\Enums\PriceType::tryFrom($plan->type)) ?? \App\Enums\PriceType::Recurring,
            highlight: (bool) $plan->is_featured,
            features: $plan->features ?? [],
            entitlements: $plan->entitlements ?? [],
            prices: $prices,
            isAvailable: true
        );
    }

    private function resolveProviderId(Price $price, string $provider): ?string
    {
        return $price->providerIds[$provider] ?? $price->contextProviderId ?? null;
    }

    private function resolveProviderValue(Price $price, string $provider, string $collectionKey, string $valueKey): mixed
    {
        if ($collectionKey === 'amounts') {
            return $price->providerAmounts[$provider] ?? $price->amount;
        }
        if ($collectionKey === 'currencies') {
            return $price->providerCurrencies[$provider] ?? $price->currency;
        }
        
        return $price->$valueKey;
    }

    private function normalizeProvider(BillingProvider|string $provider): string
    {
        return $provider instanceof BillingProvider ? $provider->value : strtolower($provider);
    }
}
