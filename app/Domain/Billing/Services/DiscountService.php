<?php

namespace App\Domain\Billing\Services;

use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\DiscountRedemption;
use App\Domain\Organization\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DiscountService
{
    public function validateForCheckout(
        string $code,
        string $provider,
        string $planKey,
        string $priceKey,
        ?string $currency
    ): Discount {
        $normalizedCode = strtoupper(trim($code));
        $provider = strtolower($provider);
        $enabledProviders = array_map('strtolower', config('saas.billing.discounts.providers', ['stripe']));

        if (!in_array($provider, $enabledProviders, true)) {
            throw ValidationException::withMessages([
                'coupon' => 'Coupons are not supported for this billing provider yet.',
            ]);
        }

        $discount = Discount::query()
            ->where('provider', $provider)
            ->where('code', $normalizedCode)
            ->first();

        if (!$discount) {
            throw ValidationException::withMessages([
                'coupon' => 'This coupon code is not available.',
            ]);
        }

        if (!$discount->is_active) {
            throw ValidationException::withMessages([
                'coupon' => 'This coupon is not active.',
            ]);
        }

        if ($discount->starts_at && now()->lt($discount->starts_at)) {
            throw ValidationException::withMessages([
                'coupon' => 'This coupon is not active yet.',
            ]);
        }

        if ($discount->ends_at && now()->gt($discount->ends_at)) {
            throw ValidationException::withMessages([
                'coupon' => 'This coupon has expired.',
            ]);
        }

        if ($discount->max_redemptions !== null && $discount->redeemed_count >= $discount->max_redemptions) {
            throw ValidationException::withMessages([
                'coupon' => 'This coupon has reached its redemption limit.',
            ]);
        }

        if (!empty($discount->plan_keys) && !in_array($planKey, $discount->plan_keys, true)) {
            throw ValidationException::withMessages([
                'coupon' => 'This coupon does not apply to the selected plan.',
            ]);
        }

        if (!empty($discount->price_keys) && !in_array($priceKey, $discount->price_keys, true)) {
            throw ValidationException::withMessages([
                'coupon' => 'This coupon does not apply to the selected price.',
            ]);
        }

        if ($discount->type === 'fixed' && $discount->currency && $currency) {
            if (strtoupper($discount->currency) !== strtoupper($currency)) {
                throw ValidationException::withMessages([
                    'coupon' => 'This coupon is not valid for the selected currency.',
                ]);
            }
        }

        if (!$discount->provider_id) {
            throw ValidationException::withMessages([
                'coupon' => 'This coupon is not configured for checkout yet.',
            ]);
        }

        return $discount;
    }

    public function recordRedemption(
        Discount $discount,
        Team $team,
        ?User $user,
        string $provider,
        string $providerId,
        ?string $planKey,
        ?string $priceKey,
        array $metadata = []
    ): void {
        DB::transaction(function () use ($discount, $team, $user, $provider, $providerId, $planKey, $priceKey, $metadata): void {
            $redemption = DiscountRedemption::query()->firstOrCreate(
                [
                    'discount_id' => $discount->id,
                    'provider' => $provider,
                    'provider_id' => $providerId,
                ],
                [
                    'team_id' => $team->id,
                    'user_id' => $user?->id,
                    'plan_key' => $planKey,
                    'price_key' => $priceKey,
                    'redeemed_at' => now(),
                    'metadata' => $metadata,
                ]
            );

            if ($redemption->wasRecentlyCreated) {
                $discount->increment('redeemed_count');
            }
        });
    }
}
