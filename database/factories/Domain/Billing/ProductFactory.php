<?php

namespace Database\Factories\Domain\Billing;

use App\Domain\Billing\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Billing\Models\Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true).' Product',
            'key' => fake()->unique()->slug(2),
            'description' => fake()->paragraph(),
            'is_active' => true,
        ];
    }
}
