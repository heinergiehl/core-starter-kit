<?php

namespace Database\Factories\Domain\Billing;

use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Models\UsageRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Billing\Models\UsageRecord>
 */
class UsageRecordFactory extends Factory
{
    protected $model = UsageRecord::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'subscription_id' => null,
            'product_id' => null,
            'price_id' => null,
            'plan_key' => fake()->slug(2),
            'price_key' => fake()->slug(2),
            'meter_key' => fake()->slug(2, '_'),
            'quantity' => fake()->numberBetween(1, 500),
            'occurred_at' => now()->subMinutes(fake()->numberBetween(1, 120)),
            'metadata' => [],
        ];
    }

    public function forSubscription(?Subscription $subscription = null): static
    {
        return $this->state(function () use ($subscription): array {
            $resolvedSubscription = $subscription ?? Subscription::factory()->create();

            return [
                'user_id' => $resolvedSubscription->user_id,
                'subscription_id' => $resolvedSubscription->id,
                'plan_key' => $resolvedSubscription->plan_key,
            ];
        });
    }
}
