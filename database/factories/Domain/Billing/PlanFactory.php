<?php

namespace Database\Factories\Domain\Billing;

use App\Domain\Billing\Models\Plan;
use App\Domain\Billing\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Billing\Models\Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    public function definition(): array
    {
        return [
            'product_id' => \App\Domain\Billing\Models\Product::factory(),
            'key' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true) . ' Plan',
            'summary' => fake()->sentence(),
            'description' => fake()->paragraph(),
            'type' => 'subscription',
            'seat_based' => false,
            'max_seats' => null,
            'is_featured' => false,
            'features' => ['Feature 1', 'Feature 2'],
            'entitlements' => [],
            'is_active' => true,
            'provider' => null,
            'provider_id' => null,
            'synced_at' => null,
        ];
    }

    public function featured(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_featured' => true,
        ]);
    }

    public function seatBased(int $maxSeats = 10): static
    {
        return $this->state(fn (array $attributes) => [
            'seat_based' => true,
            'max_seats' => $maxSeats,
        ]);
    }
}
