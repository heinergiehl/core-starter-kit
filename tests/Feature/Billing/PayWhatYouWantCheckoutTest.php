<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Contracts\BillingRuntimeProvider;
use App\Domain\Billing\Data\TransactionDTO;
use App\Domain\Billing\Models\PaymentProvider;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\Product;
use App\Domain\Billing\Services\BillingProviderManager;
use App\Enums\PriceType;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PayWhatYouWantCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(VerifyCsrfToken::class);
        $this->withoutMiddleware(ValidateCsrfToken::class);
        config(['saas.billing.pricing.shown_plans' => []]);

        PaymentProvider::query()->create([
            'name' => 'Stripe',
            'slug' => 'stripe',
            'is_active' => true,
            'configuration' => [
                'secret_key' => 'sk_test_123',
                'publishable_key' => 'pk_test_123',
                'webhook_secret' => 'whsec_test_123',
            ],
        ]);
    }

    public function test_checkout_start_shows_custom_amount_input_for_pay_what_you_want_price(): void
    {
        $product = Product::factory()->create([
            'key' => 'supporter',
            'type' => PriceType::OneTime,
            'is_active' => true,
        ]);

        Price::factory()->create([
            'product_id' => $product->id,
            'key' => 'once',
            'interval' => 'once',
            'type' => PriceType::OneTime,
            'amount' => 1500,
            'currency' => 'USD',
            'allow_custom_amount' => true,
            'custom_amount_minimum' => 500,
            'custom_amount_maximum' => 5000,
            'custom_amount_default' => 1500,
            'suggested_amounts' => [1000, 1500, 2500],
        ]);

        $response = $this->get(route('checkout.start', [
            'provider' => 'stripe',
            'plan' => 'supporter',
            'price' => 'once',
        ]));

        $response->assertOk();
        $response->assertSee('name="custom_amount"', false);
        $response->assertSee('Choose any amount between', false);
        $response->assertSee('10.00', false);
        $response->assertSee('data-pwyw-checkout', false);
        $response->assertSee('data-summary-amount', false);
    }

    public function test_checkout_start_uses_zero_decimal_currency_precision_for_pay_what_you_want_price(): void
    {
        $product = Product::factory()->create([
            'key' => 'supporter-jpy',
            'type' => PriceType::OneTime,
            'is_active' => true,
        ]);

        Price::factory()->create([
            'product_id' => $product->id,
            'key' => 'once',
            'interval' => 'once',
            'type' => PriceType::OneTime,
            'amount' => 1500,
            'currency' => 'JPY',
            'allow_custom_amount' => true,
            'custom_amount_minimum' => 500,
            'custom_amount_maximum' => 5000,
            'custom_amount_default' => 1500,
            'suggested_amounts' => [1000, 1500, 2500],
        ]);

        $response = $this->get(route('checkout.start', [
            'provider' => 'stripe',
            'plan' => 'supporter-jpy',
            'price' => 'once',
        ]));

        $response->assertOk();
        $response->assertSee('value="1500"', false);
        $response->assertSee('step="1"', false);
        $response->assertSee('data-currency-scale="0"', false);
        $response->assertSee('data-suggested-amount="1000"', false);
    }

    public function test_custom_amount_checkout_passes_minor_units_to_provider(): void
    {
        $product = Product::factory()->create([
            'key' => 'supporter',
            'type' => PriceType::OneTime,
            'is_active' => true,
        ]);

        Price::factory()->create([
            'product_id' => $product->id,
            'key' => 'once',
            'interval' => 'once',
            'type' => PriceType::OneTime,
            'amount' => 1500,
            'currency' => 'USD',
            'allow_custom_amount' => true,
            'custom_amount_minimum' => 500,
            'custom_amount_maximum' => 5000,
            'custom_amount_default' => 1500,
        ]);

        $runtime = $this->mock(BillingRuntimeProvider::class);
        $runtime->shouldReceive('createCheckout')
            ->once()
            ->withArgs(function ($user, $planKey, $priceKey, $quantity, $successUrl, $cancelUrl, $discount, $customAmountMinor): bool {
                return $user instanceof User
                    && $planKey === 'supporter'
                    && $priceKey === 'once'
                    && $quantity === 1
                    && $discount === null
                    && $customAmountMinor === 1250;
            })
            ->andReturn(new TransactionDTO(
                id: 'cs_test_custom_amount',
                url: 'https://checkout.example.test/session/cs_test_custom_amount',
                status: 'open',
            ));

        $manager = $this->mock(BillingProviderManager::class);
        $manager->shouldReceive('adapter')
            ->once()
            ->with('stripe')
            ->andReturn($runtime);

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('billing.checkout'), [
            'provider' => 'stripe',
            'plan' => 'supporter',
            'price' => 'once',
            'custom_amount' => '12.50',
            'email' => $user->email,
            'name' => $user->name,
        ]);

        $response->assertRedirect('https://checkout.example.test/session/cs_test_custom_amount');
    }

    public function test_three_decimal_currency_custom_amount_checkout_passes_minor_units_to_provider(): void
    {
        $product = Product::factory()->create([
            'key' => 'supporter-bhd',
            'type' => PriceType::OneTime,
            'is_active' => true,
        ]);

        Price::factory()->create([
            'product_id' => $product->id,
            'key' => 'once',
            'interval' => 'once',
            'type' => PriceType::OneTime,
            'amount' => 15000,
            'currency' => 'BHD',
            'allow_custom_amount' => true,
            'custom_amount_minimum' => 5000,
            'custom_amount_maximum' => 50000,
            'custom_amount_default' => 15000,
        ]);

        $runtime = $this->mock(BillingRuntimeProvider::class);
        $runtime->shouldReceive('createCheckout')
            ->once()
            ->withArgs(function ($user, $planKey, $priceKey, $quantity, $successUrl, $cancelUrl, $discount, $customAmountMinor): bool {
                return $user instanceof User
                    && $planKey === 'supporter-bhd'
                    && $priceKey === 'once'
                    && $quantity === 1
                    && $discount === null
                    && $customAmountMinor === 12345;
            })
            ->andReturn(new TransactionDTO(
                id: 'cs_test_custom_amount_bhd',
                url: 'https://checkout.example.test/session/cs_test_custom_amount_bhd',
                status: 'open',
            ));

        $manager = $this->mock(BillingProviderManager::class);
        $manager->shouldReceive('adapter')
            ->once()
            ->with('stripe')
            ->andReturn($runtime);

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('billing.checkout'), [
            'provider' => 'stripe',
            'plan' => 'supporter-bhd',
            'price' => 'once',
            'custom_amount' => '12.345',
            'email' => $user->email,
            'name' => $user->name,
        ]);

        $response->assertRedirect('https://checkout.example.test/session/cs_test_custom_amount_bhd');
    }

    public function test_custom_amount_below_minimum_is_rejected(): void
    {
        $product = Product::factory()->create([
            'key' => 'supporter',
            'type' => PriceType::OneTime,
            'is_active' => true,
        ]);

        Price::factory()->create([
            'product_id' => $product->id,
            'key' => 'once',
            'interval' => 'once',
            'type' => PriceType::OneTime,
            'amount' => 1500,
            'currency' => 'USD',
            'allow_custom_amount' => true,
            'custom_amount_minimum' => 500,
            'custom_amount_default' => 1500,
        ]);

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('billing.checkout'), [
            'provider' => 'stripe',
            'plan' => 'supporter',
            'price' => 'once',
            'custom_amount' => '1.00',
            'email' => $user->email,
            'name' => $user->name,
        ]);

        $response->assertRedirect(route('checkout.start', [
            'provider' => 'stripe',
            'plan' => 'supporter',
            'price' => 'once',
        ]));
        $response->assertSessionHasErrors('billing');
    }

    public function test_custom_amount_with_unsupported_precision_is_rejected(): void
    {
        $product = Product::factory()->create([
            'key' => 'supporter-jpy',
            'type' => PriceType::OneTime,
            'is_active' => true,
        ]);

        Price::factory()->create([
            'product_id' => $product->id,
            'key' => 'once',
            'interval' => 'once',
            'type' => PriceType::OneTime,
            'amount' => 1500,
            'currency' => 'JPY',
            'allow_custom_amount' => true,
            'custom_amount_minimum' => 500,
            'custom_amount_default' => 1500,
        ]);

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        $response = $this->actingAs($user)->post(route('billing.checkout'), [
            'provider' => 'stripe',
            'plan' => 'supporter-jpy',
            'price' => 'once',
            'custom_amount' => '12.50',
            'email' => $user->email,
            'name' => $user->name,
        ]);

        $response->assertRedirect(route('checkout.start', [
            'provider' => 'stripe',
            'plan' => 'supporter-jpy',
            'price' => 'once',
        ]));
        $response->assertSessionHasErrors('billing');
    }
}
