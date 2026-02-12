<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Contracts\BillingRuntimeProvider;
use App\Domain\Billing\Data\TransactionDTO;
use App\Domain\Billing\Models\Discount;
use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\PaymentProvider;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\PriceProviderMapping;
use App\Domain\Billing\Models\Product;
use App\Domain\Billing\Services\BillingProviderManager;
use App\Enums\OrderStatus;
use App\Enums\PriceType;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BillingCheckoutEligibilityTest extends TestCase
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

    public function test_one_time_checkout_is_blocked_when_it_is_not_an_upgrade(): void
    {
        $highTierProduct = Product::factory()->create([
            'key' => 'indie',
            'type' => PriceType::OneTime,
            'is_active' => true,
        ]);
        $highTierPrice = Price::factory()->create([
            'product_id' => $highTierProduct->id,
            'key' => 'once',
            'interval' => 'once',
            'type' => PriceType::OneTime,
            'amount' => 9900,
            'currency' => 'USD',
        ]);

        $lowTierProduct = Product::factory()->create([
            'key' => 'hobbyist',
            'type' => PriceType::OneTime,
            'is_active' => true,
        ]);
        $lowTierPrice = Price::factory()->create([
            'product_id' => $lowTierProduct->id,
            'key' => 'once',
            'interval' => 'once',
            'type' => PriceType::OneTime,
            'amount' => 4900,
            'currency' => 'USD',
        ]);

        PriceProviderMapping::query()->create([
            'price_id' => $highTierPrice->id,
            'provider' => 'stripe',
            'provider_id' => 'price_indie_once',
        ]);
        PriceProviderMapping::query()->create([
            'price_id' => $lowTierPrice->id,
            'provider' => 'stripe',
            'provider_id' => 'price_hobbyist_once',
        ]);

        $manager = $this->mock(BillingProviderManager::class);
        $manager->shouldNotReceive('adapter');

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        Order::query()->create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'provider_id' => 'pi_indie_once',
            'plan_key' => 'indie',
            'status' => OrderStatus::Paid->value,
            'amount' => 9900,
            'currency' => 'USD',
            'paid_at' => now(),
            'metadata' => [
                'price_key' => 'once',
            ],
        ]);

        $response = $this->actingAs($user)->post(route('billing.checkout'), [
            'provider' => 'stripe',
            'plan' => 'hobbyist',
            'price' => 'once',
            'email' => $user->email,
            'name' => $user->name,
        ]);

        $response->assertRedirect(route('checkout.start', [
            'provider' => 'stripe',
            'plan' => 'hobbyist',
            'price' => 'once',
        ]));
        $response->assertSessionHasErrors('billing');
    }

    public function test_one_time_customer_can_convert_to_subscription_checkout(): void
    {
        $lifetimeProduct = Product::factory()->create([
            'key' => 'starter',
            'type' => PriceType::OneTime,
            'is_active' => true,
        ]);
        $lifetimePrice = Price::factory()->create([
            'product_id' => $lifetimeProduct->id,
            'key' => 'once',
            'interval' => 'once',
            'type' => PriceType::OneTime,
            'amount' => 4900,
            'currency' => 'USD',
        ]);

        $subscriptionProduct = Product::factory()->create([
            'key' => 'pro',
            'type' => PriceType::Recurring,
            'is_active' => true,
        ]);
        $subscriptionPrice = Price::factory()->create([
            'product_id' => $subscriptionProduct->id,
            'key' => 'monthly',
            'interval' => 'month',
            'type' => PriceType::Recurring,
            'amount' => 2900,
            'currency' => 'USD',
        ]);

        PriceProviderMapping::query()->create([
            'price_id' => $lifetimePrice->id,
            'provider' => 'stripe',
            'provider_id' => 'price_starter_once',
        ]);
        PriceProviderMapping::query()->create([
            'price_id' => $subscriptionPrice->id,
            'provider' => 'stripe',
            'provider_id' => 'price_pro_monthly',
        ]);

        $runtime = $this->mock(BillingRuntimeProvider::class);
        $runtime->shouldReceive('createCheckout')
            ->once()
            ->andReturn(new TransactionDTO(
                id: 'cs_test_123',
                url: 'https://checkout.example.test/session/cs_test_123',
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

        Order::query()->create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'provider_id' => 'pi_starter_once',
            'plan_key' => 'starter',
            'status' => OrderStatus::Paid->value,
            'amount' => 4900,
            'currency' => 'USD',
            'paid_at' => now(),
            'metadata' => [
                'price_key' => 'once',
            ],
        ]);

        $response = $this->actingAs($user)->post(route('billing.checkout'), [
            'provider' => 'stripe',
            'plan' => 'pro',
            'price' => 'monthly',
            'email' => $user->email,
            'name' => $user->name,
        ]);

        $response->assertRedirect('https://checkout.example.test/session/cs_test_123');
    }

    public function test_one_time_upgrade_checkout_applies_previous_payment_as_credit(): void
    {
        $hobbyistProduct = Product::factory()->create([
            'key' => 'hobbyist',
            'type' => PriceType::OneTime,
            'is_active' => true,
        ]);
        $hobbyistPrice = Price::factory()->create([
            'product_id' => $hobbyistProduct->id,
            'key' => 'once',
            'interval' => 'once',
            'type' => PriceType::OneTime,
            'amount' => 4900,
            'currency' => 'USD',
        ]);

        $agencyProduct = Product::factory()->create([
            'key' => 'agency',
            'type' => PriceType::OneTime,
            'is_active' => true,
        ]);
        $agencyPrice = Price::factory()->create([
            'product_id' => $agencyProduct->id,
            'key' => 'once',
            'interval' => 'once',
            'type' => PriceType::OneTime,
            'amount' => 14999,
            'currency' => 'USD',
        ]);

        PriceProviderMapping::query()->create([
            'price_id' => $hobbyistPrice->id,
            'provider' => 'stripe',
            'provider_id' => 'price_hobbyist_once',
        ]);
        PriceProviderMapping::query()->create([
            'price_id' => $agencyPrice->id,
            'provider' => 'stripe',
            'provider_id' => 'price_agency_once',
        ]);

        $runtime = $this->mock(BillingRuntimeProvider::class);
        $runtime->shouldReceive('createDiscount')
            ->once()
            ->withArgs(function (Discount $discount): bool {
                return (string) $discount->provider === 'stripe'
                    && (int) $discount->amount === 4900;
            })
            ->andReturn('coupon_upgrade_credit_123');
        $runtime->shouldReceive('createCheckout')
            ->once()
            ->withArgs(function ($user, $planKey, $priceKey, $quantity, $successUrl, $cancelUrl, $discount): bool {
                return $planKey === 'agency'
                    && $priceKey === 'once'
                    && $discount instanceof Discount
                    && (int) $discount->amount === 4900
                    && $discount->provider_id === 'coupon_upgrade_credit_123';
            })
            ->andReturn(new TransactionDTO(
                id: 'cs_test_upgrade_credit',
                url: 'https://checkout.example.test/session/cs_test_upgrade_credit',
                status: 'open',
            ));

        $manager = $this->mock(BillingProviderManager::class);
        $manager->shouldReceive('adapter')
            ->twice()
            ->with('stripe')
            ->andReturn($runtime);

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        Order::query()->create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'provider_id' => 'pi_hobbyist_once',
            'plan_key' => 'hobbyist',
            'status' => OrderStatus::Paid->value,
            'amount' => 4900,
            'currency' => 'USD',
            'paid_at' => now(),
            'metadata' => [
                'price_key' => 'once',
            ],
        ]);

        $response = $this->actingAs($user)->post(route('billing.checkout'), [
            'provider' => 'stripe',
            'plan' => 'agency',
            'price' => 'once',
            'email' => $user->email,
            'name' => $user->name,
        ]);

        $response->assertRedirect('https://checkout.example.test/session/cs_test_upgrade_credit');
    }

    public function test_checkout_start_shows_upgrade_credit_summary_for_one_time_upgrade(): void
    {
        $hobbyistProduct = Product::factory()->create([
            'key' => 'hobbyist',
            'type' => PriceType::OneTime,
            'is_active' => true,
        ]);
        $hobbyistPrice = Price::factory()->create([
            'product_id' => $hobbyistProduct->id,
            'key' => 'once',
            'interval' => 'once',
            'type' => PriceType::OneTime,
            'amount' => 4900,
            'currency' => 'USD',
        ]);

        $agencyProduct = Product::factory()->create([
            'key' => 'agency',
            'type' => PriceType::OneTime,
            'is_active' => true,
        ]);
        $agencyPrice = Price::factory()->create([
            'product_id' => $agencyProduct->id,
            'key' => 'once',
            'interval' => 'once',
            'type' => PriceType::OneTime,
            'amount' => 14999,
            'currency' => 'USD',
        ]);

        PriceProviderMapping::query()->create([
            'price_id' => $hobbyistPrice->id,
            'provider' => 'stripe',
            'provider_id' => 'price_hobbyist_once',
        ]);
        PriceProviderMapping::query()->create([
            'price_id' => $agencyPrice->id,
            'provider' => 'stripe',
            'provider_id' => 'price_agency_once',
        ]);

        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);

        Order::query()->create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'provider_id' => 'pi_hobbyist_once_2',
            'plan_key' => 'hobbyist',
            'status' => OrderStatus::Paid->value,
            'amount' => 4900,
            'currency' => 'USD',
            'paid_at' => now(),
            'metadata' => [
                'price_key' => 'once',
            ],
        ]);

        $response = $this->actingAs($user)->get(route('checkout.start', [
            'provider' => 'stripe',
            'plan' => 'agency',
            'price' => 'once',
        ]));

        $response
            ->assertOk()
            ->assertSeeText('Upgrade credit')
            ->assertSeeText('Due today')
            ->assertSeeText('Your upgrade credit is applied automatically during payment.')
            ->assertDontSeeText('Promo Code');
    }
}
