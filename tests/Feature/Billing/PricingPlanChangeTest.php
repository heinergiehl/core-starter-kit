<?php

namespace Tests\Feature\Billing;

use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Price;
use App\Domain\Billing\Models\PriceProviderMapping;
use App\Domain\Billing\Models\Product;
use App\Domain\Billing\Models\Subscription;
use App\Enums\BillingProvider;
use App\Enums\OrderStatus;
use App\Enums\PriceType;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PricingPlanChangeTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_subscriber_sees_plan_switch_actions_on_pricing_page(): void
    {
        config(['saas.billing.pricing.shown_plans' => ['pro', 'growth']]);

        $proProduct = Product::factory()->create([
            'key' => 'pro',
            'name' => 'Pro',
            'type' => PriceType::Recurring,
            'is_active' => true,
        ]);
        $growthProduct = Product::factory()->create([
            'key' => 'growth',
            'name' => 'Growth',
            'type' => PriceType::Recurring,
            'is_active' => true,
        ]);

        $proPrice = Price::factory()->create([
            'product_id' => $proProduct->id,
            'key' => 'monthly',
            'interval' => 'month',
            'type' => PriceType::Recurring,
            'amount' => 2900,
            'currency' => 'USD',
        ]);
        $growthPrice = Price::factory()->create([
            'product_id' => $growthProduct->id,
            'key' => 'monthly',
            'interval' => 'month',
            'type' => PriceType::Recurring,
            'amount' => 5900,
            'currency' => 'USD',
        ]);

        PriceProviderMapping::query()->create([
            'price_id' => $proPrice->id,
            'provider' => BillingProvider::Stripe->value,
            'provider_id' => 'price_pro_monthly',
        ]);
        PriceProviderMapping::query()->create([
            'price_id' => $growthPrice->id,
            'provider' => BillingProvider::Stripe->value,
            'provider_id' => 'price_growth_monthly',
        ]);

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        Subscription::factory()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe,
            'provider_id' => 'sub_pricing_change',
            'plan_key' => 'pro',
            'status' => SubscriptionStatus::Active,
            'metadata' => [
                'stripe_price_id' => 'price_pro_monthly',
                'price_key' => 'monthly',
            ],
        ]);

        $response = $this->actingAs($user)->get(route('pricing'));

        $response
            ->assertOk()
            ->assertSeeText('Current plan')
            ->assertSeeText('Switch plan')
            ->assertSee(route('billing.change-plan', [], false), false);
    }

    public function test_active_subscriber_with_pending_plan_change_sees_pending_state_on_pricing_page(): void
    {
        config(['saas.billing.pricing.shown_plans' => ['pro', 'growth']]);

        $proProduct = Product::factory()->create([
            'key' => 'pro',
            'name' => 'Pro',
            'type' => PriceType::Recurring,
            'is_active' => true,
        ]);
        $growthProduct = Product::factory()->create([
            'key' => 'growth',
            'name' => 'Growth',
            'type' => PriceType::Recurring,
            'is_active' => true,
        ]);

        $proPrice = Price::factory()->create([
            'product_id' => $proProduct->id,
            'key' => 'monthly',
            'interval' => 'month',
            'type' => PriceType::Recurring,
            'amount' => 2900,
            'currency' => 'USD',
        ]);
        $growthPrice = Price::factory()->create([
            'product_id' => $growthProduct->id,
            'key' => 'monthly',
            'interval' => 'month',
            'type' => PriceType::Recurring,
            'amount' => 5900,
            'currency' => 'USD',
        ]);

        PriceProviderMapping::query()->create([
            'price_id' => $proPrice->id,
            'provider' => BillingProvider::Stripe->value,
            'provider_id' => 'price_pro_monthly',
        ]);
        PriceProviderMapping::query()->create([
            'price_id' => $growthPrice->id,
            'provider' => BillingProvider::Stripe->value,
            'provider_id' => 'price_growth_monthly',
        ]);

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        Subscription::factory()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe,
            'provider_id' => 'sub_pricing_pending_change',
            'plan_key' => 'pro',
            'status' => SubscriptionStatus::Active,
            'metadata' => [
                'stripe_price_id' => 'price_pro_monthly',
                'price_key' => 'monthly',
                'pending_plan_key' => 'growth',
                'pending_price_key' => 'monthly',
                'pending_provider_price_id' => 'price_growth_monthly',
            ],
        ]);

        $response = $this->actingAs($user)->get(route('pricing'));

        $response
            ->assertOk()
            ->assertSeeText('A plan change is already pending provider confirmation.')
            ->assertSeeText('Plan change pending')
            ->assertDontSee(route('billing.change-plan', [], false), false);
    }

    public function test_pending_cancellation_subscriber_sees_resume_to_change_plan_cta(): void
    {
        config(['saas.billing.pricing.shown_plans' => ['starter', 'pro']]);

        $starterProduct = Product::factory()->create([
            'key' => 'starter',
            'name' => 'Starter',
            'type' => PriceType::Recurring,
            'is_active' => true,
        ]);
        $proProduct = Product::factory()->create([
            'key' => 'pro',
            'name' => 'Pro',
            'type' => PriceType::Recurring,
            'is_active' => true,
        ]);

        $starterPrice = Price::factory()->create([
            'product_id' => $starterProduct->id,
            'key' => 'monthly',
            'interval' => 'month',
            'type' => PriceType::Recurring,
            'amount' => 2900,
            'currency' => 'USD',
        ]);
        $proPrice = Price::factory()->create([
            'product_id' => $proProduct->id,
            'key' => 'monthly',
            'interval' => 'month',
            'type' => PriceType::Recurring,
            'amount' => 5900,
            'currency' => 'USD',
        ]);

        PriceProviderMapping::query()->create([
            'price_id' => $starterPrice->id,
            'provider' => BillingProvider::Stripe->value,
            'provider_id' => 'price_starter_monthly',
        ]);
        PriceProviderMapping::query()->create([
            'price_id' => $proPrice->id,
            'provider' => BillingProvider::Stripe->value,
            'provider_id' => 'price_pro_monthly',
        ]);

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        Subscription::factory()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe,
            'provider_id' => 'sub_pricing_pending_cancellation',
            'plan_key' => 'starter',
            'status' => SubscriptionStatus::Active,
            'canceled_at' => now()->subMinute(),
            'ends_at' => now()->addDays(7),
            'metadata' => [
                'stripe_price_id' => 'price_starter_monthly',
            ],
        ]);

        $response = $this->actingAs($user)->get(route('pricing'));

        $response
            ->assertOk()
            ->assertSeeText('Resume to change plan')
            ->assertSeeText('Scheduled to cancel')
            ->assertDontSee(route('billing.change-plan', [], false), false);
    }

    public function test_one_time_customer_sees_upgrade_and_subscription_conversion_ctas(): void
    {
        config(['saas.billing.pricing.shown_plans' => ['hobbyist', 'indie', 'pro']]);

        $hobbyist = Product::factory()->create([
            'key' => 'hobbyist',
            'name' => 'Hobbyist',
            'type' => PriceType::OneTime,
            'is_active' => true,
        ]);
        $indie = Product::factory()->create([
            'key' => 'indie',
            'name' => 'Indie',
            'type' => PriceType::OneTime,
            'is_active' => true,
        ]);
        $pro = Product::factory()->create([
            'key' => 'pro',
            'name' => 'Pro',
            'type' => PriceType::Recurring,
            'is_active' => true,
        ]);

        $hobbyistPrice = Price::factory()->create([
            'product_id' => $hobbyist->id,
            'key' => 'once',
            'interval' => 'once',
            'type' => PriceType::OneTime,
            'amount' => 4900,
            'currency' => 'USD',
        ]);
        $indiePrice = Price::factory()->create([
            'product_id' => $indie->id,
            'key' => 'once',
            'interval' => 'once',
            'type' => PriceType::OneTime,
            'amount' => 9900,
            'currency' => 'USD',
        ]);
        $proPrice = Price::factory()->create([
            'product_id' => $pro->id,
            'key' => 'monthly',
            'interval' => 'month',
            'type' => PriceType::Recurring,
            'amount' => 2900,
            'currency' => 'USD',
        ]);

        foreach ([$hobbyistPrice, $indiePrice, $proPrice] as $priceModel) {
            PriceProviderMapping::query()->create([
                'price_id' => $priceModel->id,
                'provider' => BillingProvider::Stripe->value,
                'provider_id' => 'price_'.$priceModel->id,
            ]);
        }

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        Order::query()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe->value,
            'provider_id' => 'pi_one_time_owner',
            'plan_key' => 'hobbyist',
            'status' => OrderStatus::Paid->value,
            'amount' => 4900,
            'currency' => 'USD',
            'paid_at' => now(),
            'metadata' => [
                'price_key' => 'once',
            ],
        ]);

        $response = $this->actingAs($user)->get(route('pricing'));

        $response
            ->assertOk()
            ->assertSeeText('Upgrade One-time')
            ->assertSeeText('Switch to Subscription')
            ->assertSeeText('You already own this one-time plan.');
    }

    public function test_discounted_one_time_upgrade_still_treats_customer_as_higher_tier_owner(): void
    {
        config(['saas.billing.pricing.shown_plans' => ['hobbyist', 'indie', 'agency']]);

        $hobbyist = Product::factory()->create([
            'key' => 'hobbyist',
            'name' => 'Hobbyist',
            'type' => PriceType::OneTime,
            'is_active' => true,
        ]);
        $indie = Product::factory()->create([
            'key' => 'indie',
            'name' => 'Indie',
            'type' => PriceType::OneTime,
            'is_active' => true,
        ]);
        $agency = Product::factory()->create([
            'key' => 'agency',
            'name' => 'Agency',
            'type' => PriceType::OneTime,
            'is_active' => true,
        ]);

        $hobbyistPrice = Price::factory()->create([
            'product_id' => $hobbyist->id,
            'key' => 'once',
            'interval' => 'once',
            'type' => PriceType::OneTime,
            'amount' => 4900,
            'currency' => 'USD',
        ]);
        $indiePrice = Price::factory()->create([
            'product_id' => $indie->id,
            'key' => 'once',
            'interval' => 'once',
            'type' => PriceType::OneTime,
            'amount' => 9999,
            'currency' => 'USD',
        ]);
        $agencyPrice = Price::factory()->create([
            'product_id' => $agency->id,
            'key' => 'once',
            'interval' => 'once',
            'type' => PriceType::OneTime,
            'amount' => 14999,
            'currency' => 'USD',
        ]);

        foreach ([$hobbyistPrice, $indiePrice, $agencyPrice] as $priceModel) {
            PriceProviderMapping::query()->create([
                'price_id' => $priceModel->id,
                'provider' => BillingProvider::Stripe->value,
                'provider_id' => 'price_'.$priceModel->id,
            ]);
        }

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        // User upgraded to agency with credit and paid only the delta.
        Order::query()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe->value,
            'provider_id' => 'pi_agency_delta_paid',
            'plan_key' => 'agency',
            'status' => OrderStatus::Paid->value,
            'amount' => 5000,
            'currency' => 'USD',
            'paid_at' => now(),
            'metadata' => [
                'metadata' => [
                    'price_key' => 'once',
                ],
            ],
        ]);

        $response = $this->actingAs($user)->get(route('pricing'));

        $response
            ->assertOk()
            ->assertSeeText('You already own this one-time plan.')
            ->assertSeeText('One-time downgrades are not available in self-serve checkout. Please contact support.')
            ->assertDontSeeText('Upgrade One-time');
    }

    public function test_one_time_downgrade_attempt_shows_support_contact_cta(): void
    {
        config([
            'saas.billing.pricing.shown_plans' => ['hobbyist', 'indie'],
            'saas.support.email' => 'support@example.test',
        ]);

        $hobbyist = Product::factory()->create([
            'key' => 'hobbyist',
            'name' => 'Hobbyist',
            'type' => PriceType::OneTime,
            'is_active' => true,
        ]);
        $indie = Product::factory()->create([
            'key' => 'indie',
            'name' => 'Indie',
            'type' => PriceType::OneTime,
            'is_active' => true,
        ]);

        $hobbyistPrice = Price::factory()->create([
            'product_id' => $hobbyist->id,
            'key' => 'once',
            'interval' => 'once',
            'type' => PriceType::OneTime,
            'amount' => 4900,
            'currency' => 'USD',
        ]);
        $indiePrice = Price::factory()->create([
            'product_id' => $indie->id,
            'key' => 'once',
            'interval' => 'once',
            'type' => PriceType::OneTime,
            'amount' => 9900,
            'currency' => 'USD',
        ]);

        foreach ([$hobbyistPrice, $indiePrice] as $priceModel) {
            PriceProviderMapping::query()->create([
                'price_id' => $priceModel->id,
                'provider' => BillingProvider::Stripe->value,
                'provider_id' => 'price_'.$priceModel->id,
            ]);
        }

        $user = User::factory()->create([
            'email_verified_at' => now(),
            'onboarding_completed_at' => now(),
        ]);

        Order::query()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe->value,
            'provider_id' => 'pi_one_time_indie_owner',
            'plan_key' => 'indie',
            'status' => OrderStatus::Paid->value,
            'amount' => 9900,
            'currency' => 'USD',
            'paid_at' => now(),
            'metadata' => [
                'price_key' => 'once',
            ],
        ]);

        $response = $this->actingAs($user)->get(route('pricing'));

        $response
            ->assertOk()
            ->assertSeeText('One-time downgrades are not available in self-serve checkout. Please contact support.')
            ->assertSeeText('Contact support ->');
    }

    public function test_pricing_interval_toggle_supports_weekly_plans(): void
    {
        config(['saas.billing.pricing.shown_plans' => ['starter']]);

        $starter = Product::factory()->create([
            'key' => 'starter',
            'name' => 'Starter',
            'type' => PriceType::Recurring,
            'is_active' => true,
        ]);

        $monthly = Price::factory()->create([
            'product_id' => $starter->id,
            'key' => 'monthly',
            'interval' => 'month',
            'type' => PriceType::Recurring,
            'amount' => 2900,
            'currency' => 'USD',
        ]);
        $weekly = Price::factory()->create([
            'product_id' => $starter->id,
            'key' => 'weekly',
            'interval' => 'week',
            'type' => PriceType::Recurring,
            'amount' => 900,
            'currency' => 'USD',
        ]);

        foreach ([$monthly, $weekly] as $priceModel) {
            PriceProviderMapping::query()->create([
                'price_id' => $priceModel->id,
                'provider' => BillingProvider::Stripe->value,
                'provider_id' => 'price_'.$priceModel->id,
            ]);
        }

        $response = $this->get(route('pricing'));

        $response
            ->assertOk()
            ->assertSeeText('Monthly')
            ->assertSeeText('Weekly');
    }
}
