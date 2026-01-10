<?php

namespace Database\Factories\Domain\Billing;

use App\Domain\Billing\Models\Subscription;
use App\Domain\Organization\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Billing\Models\Subscription>
 */
class SubscriptionFactory extends Factory
{
    protected $model = Subscription::class;

    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'provider' => fake()->randomElement(['stripe', 'lemonsqueezy', 'paddle']),
            'provider_id' => 'sub_' . fake()->uuid(),
            'plan_key' => 'pro-monthly',
            'status' => 'active',
            'quantity' => 1,
            'trial_ends_at' => null,
            'renews_at' => now()->addMonth(),
            'ends_at' => null,
            'canceled_at' => null,
            'metadata' => [],
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
            'canceled_at' => null,
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'canceled',
            'canceled_at' => now(),
        ]);
    }

    public function trialing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'trialing',
            'trial_ends_at' => now()->addDays(14),
        ]);
    }
}
