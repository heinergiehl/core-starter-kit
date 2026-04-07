<?php

namespace Database\Factories\Domain\Billing;

use App\Domain\Billing\Models\Subscription;
use App\Models\User;
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
            'user_id' => User::factory(),
            'provider' => fake()->randomElement(\App\Enums\BillingProvider::cases()),
            'provider_id' => 'sub_'.fake()->uuid(),
            'plan_key' => 'pro-monthly',
            'status' => \App\Enums\SubscriptionStatus::Active,
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
            'status' => \App\Enums\SubscriptionStatus::Active,
            'canceled_at' => null,
        ]);
    }

    public function canceled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => \App\Enums\SubscriptionStatus::Canceled,
            'canceled_at' => now(),
        ]);
    }

    public function trialing(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => \App\Enums\SubscriptionStatus::Trialing,
            'trial_ends_at' => now()->addDays(14),
        ]);
    }

    public function pastDue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => \App\Enums\SubscriptionStatus::PastDue,
            'renews_at' => now()->subDays(3),
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => \App\Enums\SubscriptionStatus::Paused,
            'renews_at' => null,
        ]);
    }

    public function unpaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => \App\Enums\SubscriptionStatus::Unpaid,
        ]);
    }

    public function incomplete(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => \App\Enums\SubscriptionStatus::Incomplete,
        ]);
    }

    public function incompleteExpired(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => \App\Enums\SubscriptionStatus::IncompleteExpired,
            'ends_at' => now()->subDay(),
        ]);
    }

    public function pendingCancellation(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => \App\Enums\SubscriptionStatus::Canceled,
            'canceled_at' => now(),
            'ends_at' => now()->addDays(15),
        ]);
    }
}
