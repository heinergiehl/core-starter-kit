<?php

namespace Database\Factories\Domain\Billing;

use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Billing\Models\Price>
 */
class PriceFactory extends Factory
{
    protected $model = Price::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'key' => fake()->unique()->slug(2),
            // 'provider' => removed
            // 'provider_id' => removed
            'label' => 'Monthly',
            'interval' => 'month',
            'interval_count' => 1,
            'currency' => 'usd',
            'amount' => fake()->randomElement([990, 1990, 2990, 4900, 9900]),
            'type' => \App\Enums\PriceType::Recurring,
            'has_trial' => false,
            'trial_interval' => null,
            'trial_interval_count' => null,
            'is_active' => true,
        ];
    }

    public function yearly(): static
    {
        return $this->state(fn (array $attributes) => [
            'interval' => 'year',
            'interval_count' => 1,
            'label' => 'Yearly',
        ]);
    }

    public function withTrial(int $days = 14): static
    {
        return $this->state(fn (array $attributes) => [
            'has_trial' => true,
            'trial_interval' => 'day',
            'trial_interval_count' => $days,
        ]);
    }
}
