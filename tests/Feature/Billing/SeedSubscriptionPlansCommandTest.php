<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use App\Enums\PriceType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SeedSubscriptionPlansCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_seeds_recurring_plans_using_shown_plan_keys(): void
    {
        config(['saas.billing.pricing.shown_plans' => ['hobbyist', 'indie', 'agency']]);

        $hobbyist = Product::query()->create([
            'key' => 'hobbyist',
            'name' => 'Hobbyist',
            'type' => PriceType::OneTime->value,
            'is_active' => true,
        ]);

        Price::query()->create([
            'product_id' => $hobbyist->id,
            'key' => 'monthly',
            'label' => 'Monthly',
            'interval' => 'month',
            'interval_count' => 1,
            'currency' => 'USD',
            'amount' => 4900,
            'type' => PriceType::Recurring->value,
            'is_active' => true,
        ]);

        $this->artisan('billing:seed-subscription-plans --force')
            ->expectsOutputToContain('Seeding recurring subscription plans')
            ->assertExitCode(0);

        foreach (['hobbyist', 'indie', 'agency'] as $key) {
            $this->assertDatabaseHas('products', [
                'key' => $key,
                'type' => PriceType::OneTime->value,
                'is_active' => true,
            ]);

            $product = Product::query()->where('key', $key)->firstOrFail();

            $this->assertDatabaseHas('prices', [
                'product_id' => $product->id,
                'key' => 'once',
                'interval' => 'once',
                'type' => PriceType::OneTime->value,
                'is_active' => true,
            ]);
        }

        $this->assertDatabaseHas('prices', [
            'product_id' => $hobbyist->id,
            'key' => 'monthly',
            'is_active' => false,
        ]);
    }

    public function test_command_uses_default_keys_when_shown_plan_keys_are_missing(): void
    {
        config(['saas.billing.pricing.shown_plans' => []]);

        $this->artisan('billing:seed-subscription-plans --force')
            ->assertExitCode(0);

        foreach (['starter', 'pro', 'growth'] as $key) {
            $this->assertDatabaseHas('products', [
                'key' => $key,
                'type' => PriceType::OneTime->value,
                'is_active' => true,
            ]);
        }
    }
}
