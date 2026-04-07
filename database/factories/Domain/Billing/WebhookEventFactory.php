<?php

namespace Database\Factories\Domain\Billing;

use App\Domain\Billing\Models\WebhookEvent;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Billing\Models\WebhookEvent>
 */
class WebhookEventFactory extends Factory
{
    protected $model = WebhookEvent::class;

    public function definition(): array
    {
        return [
            'provider' => fake()->randomElement(['stripe', 'paddle']),
            'event_id' => 'evt_'.fake()->uuid(),
            'type' => fake()->randomElement([
                'checkout.session.completed',
                'invoice.paid',
                'customer.subscription.updated',
                'customer.subscription.deleted',
            ]),
            'payload' => ['id' => 'evt_'.fake()->uuid(), 'type' => 'checkout.session.completed'],
            'status' => 'received',
            'error_message' => null,
            'received_at' => now(),
            'processed_at' => null,
        ];
    }

    public function processed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'processed',
            'processed_at' => now(),
        ]);
    }

    public function failed(string $error = 'Processing failed'): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'error_message' => $error,
        ]);
    }

    public function stripe(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'stripe',
        ]);
    }

    public function paddle(): static
    {
        return $this->state(fn (array $attributes) => [
            'provider' => 'paddle',
        ]);
    }
}
