<?php

namespace Database\Factories\Domain\Billing;

use App\Domain\Billing\Models\Invoice;
use App\Domain\Billing\Models\Subscription;
use App\Enums\BillingProvider;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Domain\Billing\Models\Invoice>
 */
class InvoiceFactory extends Factory
{
    protected $model = Invoice::class;

    public function definition(): array
    {
        $amount = fake()->randomElement([990, 1990, 2990, 4900, 9900]);

        return [
            'user_id' => User::factory(),
            'subscription_id' => null,
            'order_id' => null,
            'provider' => fake()->randomElement(BillingProvider::cases()),
            'provider_id' => 'inv_'.fake()->uuid(),
            'provider_invoice_id' => null,
            'invoice_number' => 'INV-'.fake()->unique()->numerify('######'),
            'status' => 'paid',
            'customer_name' => fake()->name(),
            'customer_email' => fake()->email(),
            'customer_vat_number' => null,
            'billing_address' => [],
            'amount_due' => $amount,
            'amount_paid' => $amount,
            'subtotal' => $amount,
            'tax_amount' => 0,
            'tax_rate' => 0,
            'currency' => 'usd',
            'issued_at' => now(),
            'due_at' => now()->addDays(30),
            'paid_at' => now(),
            'hosted_invoice_url' => null,
            'pdf_url' => null,
            'pdf_url_expires_at' => null,
            'metadata' => [],
        ];
    }

    public function forSubscription(?Subscription $subscription = null): static
    {
        return $this->state(fn (array $attributes) => [
            'subscription_id' => $subscription?->id ?? Subscription::factory(),
            'user_id' => $subscription?->user_id ?? $attributes['user_id'],
        ]);
    }

    public function unpaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'open',
            'amount_paid' => 0,
            'paid_at' => null,
        ]);
    }

    public function overdue(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'open',
            'amount_paid' => 0,
            'paid_at' => null,
            'due_at' => now()->subDays(7),
        ]);
    }
}
