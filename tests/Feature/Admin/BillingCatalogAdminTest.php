<?php

namespace Tests\Feature\Admin;

use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use App\Enums\PriceType;
use App\Enums\UsageLimitBehavior;
use App\Filament\Admin\Resources\PriceResource\Pages\EditPrice;
use App\Filament\Admin\Resources\ProductResource\Pages\CreateProduct;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class BillingCatalogAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_product_with_a_pay_what_you_want_price(): void
    {
        $this->authenticateAdmin();

        $component = Livewire::test(CreateProduct::class)
            ->fillForm([
                'key' => 'supporter',
                'name' => 'Supporter',
                'summary' => 'Flexible support checkout',
                'description' => 'One-time support payment with custom amount support.',
                'type' => 'one_time',
                'is_active' => true,
            ]);

        $component
            ->set('data.prices', [
                'supporter-price' => [
                    'pricing_mode' => 'one_time_pwyw',
                    'key' => 'supporter_once',
                    'amount' => '15.00',
                    'label' => 'Supporter',
                    'currency' => 'USD',
                    'custom_amount_default' => '15.00',
                    'custom_amount_minimum' => '5.00',
                    'custom_amount_maximum' => '50.00',
                    'suggested_amounts' => "10.00\n15.00\n25.00",
                    'is_active' => true,
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('key', 'supporter')->first();

        $this->assertNotNull($product);
        $this->assertSame('one_time', $product->type);

        $price = $product->prices()->first();

        $this->assertNotNull($price);
        $this->assertTrue($price->allow_custom_amount);
        $this->assertSame(PriceType::OneTime, $price->type);
        $this->assertSame([1000, 1500, 2500], $price->suggested_amounts);
    }

    public function test_admin_can_edit_pay_what_you_want_price_constraints(): void
    {
        $this->authenticateAdmin();

        $product = Product::factory()->create([
            'type' => 'one_time',
        ]);

        $price = Price::factory()->create([
            'product_id' => $product->id,
            'interval' => 'once',
            'type' => PriceType::OneTime,
            'allow_custom_amount' => true,
            'custom_amount_default' => 1500,
            'custom_amount_minimum' => 500,
            'custom_amount_maximum' => 5000,
            'suggested_amounts' => [1000, 1500, 2500],
        ]);

        Livewire::test(EditPrice::class, ['record' => $price->getRouteKey()])
            ->fillForm([
                'pricing_mode' => 'one_time_pwyw',
                'custom_amount_default' => '20.00',
                'custom_amount_minimum' => '7.00',
                'custom_amount_maximum' => '90.00',
                'suggested_amounts' => "7.00\n20.00\n50.00",
                'is_active' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $price->refresh();

        $this->assertSame(2000, $price->custom_amount_default);
        $this->assertSame(700, $price->custom_amount_minimum);
        $this->assertSame(9000, $price->custom_amount_maximum);
        $this->assertSame([700, 2000, 5000], $price->suggested_amounts);
    }

    public function test_admin_can_store_pay_what_you_want_amounts_for_three_decimal_currencies(): void
    {
        $this->authenticateAdmin();

        $product = Product::factory()->create([
            'type' => 'one_time',
        ]);

        $price = Price::factory()->create([
            'product_id' => $product->id,
            'currency' => 'BHD',
            'interval' => 'once',
            'type' => PriceType::OneTime,
            'allow_custom_amount' => true,
            'custom_amount_default' => 15000,
            'custom_amount_minimum' => 5000,
            'custom_amount_maximum' => 50000,
            'suggested_amounts' => [10000, 15000, 25000],
        ]);

        Livewire::test(EditPrice::class, ['record' => $price->getRouteKey()])
            ->fillForm([
                'pricing_mode' => 'one_time_pwyw',
                'custom_amount_default' => '12.345',
                'custom_amount_minimum' => '5.000',
                'custom_amount_maximum' => '20.500',
                'suggested_amounts' => "5.000\n12.345\n20.500",
                'is_active' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $price->refresh();

        $this->assertSame(12345, $price->custom_amount_default);
        $this->assertSame(5000, $price->custom_amount_minimum);
        $this->assertSame(20500, $price->custom_amount_maximum);
        $this->assertSame([5000, 12345, 20500], $price->suggested_amounts);
    }

    public function test_admin_cannot_save_invalid_pay_what_you_want_ranges(): void
    {
        $this->authenticateAdmin();

        $product = Product::factory()->create([
            'type' => 'one_time',
        ]);

        $price = Price::factory()->create([
            'product_id' => $product->id,
            'interval' => 'once',
            'type' => PriceType::OneTime,
            'allow_custom_amount' => true,
            'custom_amount_default' => 1500,
            'custom_amount_minimum' => 500,
            'custom_amount_maximum' => 5000,
            'suggested_amounts' => [1000, 1500, 2500],
        ]);

        Livewire::test(EditPrice::class, ['record' => $price->getRouteKey()])
            ->fillForm([
                'pricing_mode' => 'one_time_pwyw',
                'custom_amount_default' => '2.00',
                'custom_amount_minimum' => '5.00',
                'custom_amount_maximum' => '4.00',
                'suggested_amounts' => "3.00\n6.00",
            ])
            ->call('save')
            ->assertHasFormErrors([
                'custom_amount_default',
                'custom_amount_minimum',
                'custom_amount_maximum',
                'suggested_amounts',
            ]);
    }

    public function test_admin_can_switch_pay_what_you_want_price_back_to_fixed_one_time(): void
    {
        $this->authenticateAdmin();

        $product = Product::factory()->create([
            'type' => 'one_time',
        ]);

        $price = Price::factory()->create([
            'product_id' => $product->id,
            'interval' => 'once',
            'type' => PriceType::OneTime,
            'allow_custom_amount' => true,
            'custom_amount_default' => 1500,
            'custom_amount_minimum' => 500,
            'custom_amount_maximum' => 5000,
            'suggested_amounts' => [1000, 1500, 2500],
        ]);

        Livewire::test(EditPrice::class, ['record' => $price->getRouteKey()])
            ->fillForm([
                'pricing_mode' => 'one_time_fixed',
                'is_active' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $price->refresh();

        $this->assertFalse($price->allow_custom_amount);
        $this->assertNull($price->custom_amount_default);
        $this->assertNull($price->custom_amount_minimum);
        $this->assertNull($price->custom_amount_maximum);
        $this->assertNull($price->suggested_amounts);
    }

    public function test_admin_can_open_the_product_edit_page_after_enabling_pay_what_you_want_pricing(): void
    {
        $this->authenticateAdmin();

        $product = Product::factory()->create([
            'type' => 'one_time',
        ]);

        Price::factory()->create([
            'product_id' => $product->id,
            'interval' => 'once',
            'type' => PriceType::OneTime,
            'allow_custom_amount' => true,
            'custom_amount_default' => 1500,
        ]);

        $this->get("/admin/products/{$product->getRouteKey()}/edit")
            ->assertOk()
            ->assertSeeText('Product Details');
    }

    public function test_admin_can_create_a_product_with_a_usage_based_price(): void
    {
        $this->authenticateAdmin();

        $component = Livewire::test(CreateProduct::class)
            ->fillForm([
                'key' => 'scale',
                'name' => 'Scale',
                'summary' => 'Recurring base fee plus metered overages.',
                'description' => 'Usage-based pricing for API-heavy customers.',
                'type' => 'subscription',
                'is_active' => true,
            ]);

        $component
            ->set('data.prices', [
                'scale-metered' => [
                    'pricing_mode' => 'usage_based',
                    'key' => 'metered_monthly',
                    'interval' => 'month',
                    'interval_count' => 1,
                    'amount' => '49.00',
                    'label' => 'Metered Monthly',
                    'currency' => 'USD',
                    'usage_meter_name' => 'API requests',
                    'usage_meter_key' => 'api_requests',
                    'usage_unit_label' => 'request',
                    'usage_included_units' => '10000',
                    'usage_limit_behavior' => 'bill_overage',
                    'usage_package_size' => '1000',
                    'usage_overage_amount' => '5.00',
                    'usage_rounding_mode' => 'up',
                    'is_active' => true,
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $product = Product::query()->where('key', 'scale')->first();

        $this->assertNotNull($product);
        $this->assertSame('subscription', $product->type);

        $price = $product->prices()->first();

        $this->assertNotNull($price);
        $this->assertSame(PriceType::Recurring, $price->type);
        $this->assertTrue($price->is_metered);
        $this->assertFalse($price->allow_custom_amount);
        $this->assertSame('api_requests', $price->usage_meter_key);
        $this->assertSame(10000, $price->usage_included_units);
        $this->assertSame(UsageLimitBehavior::BillOverage, $price->usage_limit_behavior);
        $this->assertSame(1000, $price->usage_package_size);
        $this->assertSame(500, $price->usage_overage_amount);
    }

    public function test_admin_can_edit_usage_based_pricing_details(): void
    {
        $this->authenticateAdmin();

        $product = Product::factory()->create([
            'type' => 'subscription',
        ]);

        $price = Price::factory()->metered()->create([
            'product_id' => $product->id,
            'interval' => 'month',
            'type' => PriceType::Recurring,
            'amount' => 4900,
            'currency' => 'USD',
        ]);

        Livewire::test(EditPrice::class, ['record' => $price->getRouteKey()])
            ->fillForm([
                'pricing_mode' => 'usage_based',
                'usage_meter_name' => 'Tracked seats',
                'usage_meter_key' => 'tracked_seats',
                'usage_unit_label' => 'seat',
                'usage_included_units' => '25',
                'usage_package_size' => '5',
                'usage_overage_amount' => '19.00',
                'usage_rounding_mode' => 'down',
                'is_active' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $price->refresh();

        $this->assertTrue($price->is_metered);
        $this->assertSame('Tracked seats', $price->usage_meter_name);
        $this->assertSame('tracked_seats', $price->usage_meter_key);
        $this->assertSame('seat', $price->usage_unit_label);
        $this->assertSame(25, $price->usage_included_units);
        $this->assertSame(5, $price->usage_package_size);
        $this->assertSame(1900, $price->usage_overage_amount);
        $this->assertSame('down', $price->usage_rounding_mode);
    }

    public function test_admin_can_set_usage_based_pricing_to_block_at_limit(): void
    {
        $this->authenticateAdmin();

        $product = Product::factory()->create([
            'type' => 'subscription',
        ]);

        $price = Price::factory()->metered()->create([
            'product_id' => $product->id,
            'interval' => 'month',
            'type' => PriceType::Recurring,
            'amount' => 4900,
            'currency' => 'USD',
        ]);

        Livewire::test(EditPrice::class, ['record' => $price->getRouteKey()])
            ->fillForm([
                'pricing_mode' => 'usage_based',
                'usage_meter_name' => 'Tracked seats',
                'usage_meter_key' => 'tracked_seats',
                'usage_unit_label' => 'seat',
                'usage_included_units' => '25',
                'usage_limit_behavior' => 'block',
                'is_active' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $price->refresh();

        $this->assertTrue($price->is_metered);
        $this->assertSame(UsageLimitBehavior::Block, $price->usage_limit_behavior);
        $this->assertSame(25, $price->usage_included_units);
    }

    private function authenticateAdmin(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        $this->actingAs($admin);
        Filament::setCurrentPanel('admin');
        Filament::bootCurrentPanel();
    }
}
