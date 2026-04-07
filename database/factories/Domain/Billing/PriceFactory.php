<?php

namespace Database\Factories\Domain\Billing;

use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use App\Enums\UsageLimitBehavior;
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
            'allow_custom_amount' => false,
            'is_metered' => false,
            'usage_meter_name' => null,
            'usage_meter_key' => null,
            'usage_unit_label' => null,
            'usage_included_units' => null,
            'usage_package_size' => null,
            'usage_overage_amount' => null,
            'usage_rounding_mode' => null,
            'usage_limit_behavior' => UsageLimitBehavior::BillOverage,
            'custom_amount_minimum' => null,
            'custom_amount_maximum' => null,
            'custom_amount_default' => null,
            'suggested_amounts' => null,
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

    public function metered(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_metered' => true,
            'usage_meter_name' => 'API requests',
            'usage_meter_key' => 'api_requests',
            'usage_unit_label' => 'request',
            'usage_included_units' => 10000,
            'usage_package_size' => 1000,
            'usage_overage_amount' => 500,
            'usage_rounding_mode' => 'up',
            'usage_limit_behavior' => UsageLimitBehavior::BillOverage,
        ]);
    }
}
