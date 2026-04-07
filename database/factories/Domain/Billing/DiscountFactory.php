<?php

namespace Database\Factories\Domain\Billing;

use App\Domain\Billing\Models\Discount;
use App\Enums\DiscountType;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Billing\Models\Discount>
 */
class DiscountFactory extends Factory
{
    protected $model = Discount::class;

    public function definition(): array
    {
        return [
            'code' => strtoupper(fake()->bothify('SAVE##??')),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'provider' => 'stripe',
            'provider_id' => null,
            'provider_type' => 'coupon',
            'type' => DiscountType::Percent,
            'amount' => fake()->randomElement([10, 15, 20, 25, 50]),
            'currency' => null,
            'max_redemptions' => null,
            'redeemed_count' => 0,
            'is_active' => true,
            'starts_at' => null,
            'ends_at' => null,
            'plan_keys' => [],
            'price_keys' => [],
            'metadata' => [],
        ];
    }

    public function percentage(int $amount = 20): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DiscountType::Percent,
            'amount' => $amount,
            'currency' => null,
        ]);
    }

    public function fixed(int $amountMinor = 1000, string $currency = 'usd'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => DiscountType::Fixed,
            'amount' => $amountMinor,
            'currency' => strtoupper($currency),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn (array $attributes) => [
            'ends_at' => now()->subDay(),
            'is_active' => false,
        ]);
    }

    public function limited(int $max = 100): static
    {
        return $this->state(fn (array $attributes) => [
            'max_redemptions' => $max,
        ]);
    }

    public function forPlan(string $planKey): static
    {
        return $this->state(fn (array $attributes) => [
            'plan_keys' => [$planKey],
        ]);
    }
}
