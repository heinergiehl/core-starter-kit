<?php

namespace Database\Factories\Domain\Billing;

use App\Domain\Billing\Models\Order;
use App\Enums\BillingProvider;
use App\Enums\OrderStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Billing\Models\Order>
 */
class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => fake()->randomElement(BillingProvider::cases()),
            'provider_id' => 'ord_'.fake()->uuid(),
            'plan_key' => 'pro-lifetime',
            'status' => OrderStatus::Paid,
            'amount' => fake()->randomElement([4900, 9900, 19900, 49900]),
            'currency' => 'usd',
            'paid_at' => now(),
            'refunded_at' => null,
            'metadata' => [],
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Completed,
            'paid_at' => now(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Pending,
            'paid_at' => null,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Failed,
            'paid_at' => null,
        ]);
    }

    public function refunded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => OrderStatus::Refunded,
            'paid_at' => now()->subWeek(),
            'refunded_at' => now(),
        ]);
    }
}
