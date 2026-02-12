<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Data\Plan;
use App\Domain\Billing\Data\Price;
use App\Domain\Billing\Exceptions\BillingException;
use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\CheckoutService;
use App\Enums\OrderStatus;
use App\Enums\PriceType;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CheckoutServiceTest extends TestCase
{
    use RefreshDatabase;

    protected CheckoutService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(CheckoutService::class);
    }

    #[Test]
    public function it_creates_user_for_guest()
    {
        Event::fake([Registered::class]);

        $request = new Request;
        $email = 'guest@example.com';
        $name = 'Guest User';

        $result = $this->service->resolveOrCreateUser($request, $email, $name);

        $this->assertTrue($result->created);
        $this->assertInstanceOf(User::class, $result->user);
        $this->assertEquals($email, $result->user->email);
        $this->assertDatabaseHas('users', ['email' => $email]);
    }

    #[Test]
    public function it_reuses_existing_user_without_purchase()
    {
        // Create user with NO purchases (abandoned checkout scenario)
        $user = User::factory()->create(['email' => 'abandoned@example.com']);
        $request = new Request;

        $result = $this->service->resolveOrCreateUser($request, 'abandoned@example.com', 'Abandoned User');

        // Should reuse existing user
        $this->assertFalse($result->created);
        $this->assertEquals($user->id, $result->user->id);
    }

    #[Test]
    public function it_throws_exception_for_user_with_existing_subscription()
    {
        // Create user WITH a subscription
        $user = User::factory()->create(['email' => 'subscribed@example.com']);
        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
        ]);
        $request = new Request;

        $this->expectException(BillingException::class);

        $this->service->resolveOrCreateUser($request, 'subscribed@example.com', 'Subscribed User');
    }

    #[Test]
    public function it_returns_existing_user_if_authenticated()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = new Request;
        $request->setUserResolver(fn () => $user);

        $result = $this->service->resolveOrCreateUser($request, 'any@email.com', 'Any Name');

        $this->assertFalse($result->created);
        $this->assertEquals($user->id, $result->user->id);
    }

    #[Test]
    public function it_builds_paddle_session_data_from_plan_and_price_dtos(): void
    {
        $plan = new Plan(
            key: 'starter',
            name: 'Starter',
            summary: '',
            type: PriceType::Recurring,
            highlight: false,
            features: [],
            entitlements: [],
            prices: [],
        );

        $price = new Price(
            key: 'monthly',
            label: 'Monthly',
            amount: 4900,
            currency: 'USD',
            interval: 'month',
            intervalCount: 1,
            type: PriceType::Recurring,
            hasTrial: false,
            trialInterval: null,
            trialIntervalCount: null,
            amountIsMinor: true,
        );

        $data = $this->service->buildPaddleSessionData(
            provider: 'paddle',
            planKey: 'starter',
            priceKey: 'monthly',
            plan: $plan,
            price: $price,
            priceCurrency: 'USD',
            quantity: 1,
            providerPriceId: 'pri_123',
            transactionId: 'txn_123',
        );

        $this->assertSame('Starter', $data['plan_name']);
        $this->assertSame('Monthly', $data['price_label']);
        $this->assertSame(4900, $data['amount']);
        $this->assertTrue($data['amount_is_minor']);
    }

    #[Test]
    public function it_blocks_checkout_when_user_has_active_subscription(): void
    {
        $user = User::factory()->create();

        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $plan = $this->recurringPlan('pro', 4900);
        $price = $plan->getPrice('monthly');

        $eligibility = $this->service->evaluateCheckoutEligibility($user, $plan, $price);

        $this->assertFalse($eligibility->allowed);
        $this->assertSame('BILLING_CHECKOUT_BLOCKED_ACTIVE_SUBSCRIPTION', $eligibility->errorCode);
    }

    #[Test]
    public function it_allows_one_time_customer_to_convert_to_subscription(): void
    {
        $user = User::factory()->create();

        Order::query()->create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'provider_id' => 'pi_convert_'.uniqid(),
            'plan_key' => 'starter-lifetime',
            'status' => OrderStatus::Paid->value,
            'amount' => 4900,
            'currency' => 'USD',
            'paid_at' => now(),
        ]);

        $plan = $this->recurringPlan('pro', 9900);
        $price = $plan->getPrice('monthly');

        $eligibility = $this->service->evaluateCheckoutEligibility($user, $plan, $price);

        $this->assertTrue($eligibility->allowed);
        $this->assertTrue($eligibility->isUpgrade);
    }

    #[Test]
    public function it_blocks_one_time_downgrade_in_self_serve_checkout(): void
    {
        $user = User::factory()->create();

        Order::query()->create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'provider_id' => 'pi_current_'.uniqid(),
            'plan_key' => 'indie',
            'status' => OrderStatus::Paid->value,
            'amount' => 9900,
            'currency' => 'USD',
            'paid_at' => now(),
            'metadata' => [
                'price_key' => 'once',
            ],
        ]);

        $plan = $this->oneTimePlan('hobbyist', 4900);
        $price = $plan->getPrice('once');

        $eligibility = $this->service->evaluateCheckoutEligibility($user, $plan, $price);

        $this->assertFalse($eligibility->allowed);
        $this->assertSame('BILLING_ONE_TIME_DOWNGRADE_UNSUPPORTED', $eligibility->errorCode);
        $this->assertStringContainsString('contact support', strtolower((string) $eligibility->message));
    }

    #[Test]
    public function it_blocks_one_time_purchase_when_amount_is_not_higher(): void
    {
        $user = User::factory()->create();

        Order::query()->create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'provider_id' => 'pi_same_amount_'.uniqid(),
            'plan_key' => 'indie',
            'status' => OrderStatus::Paid->value,
            'amount' => 9900,
            'currency' => 'USD',
            'paid_at' => now(),
            'metadata' => [
                'price_key' => 'once',
            ],
        ]);

        $plan = $this->oneTimePlan('agency', 9900);
        $price = $plan->getPrice('once');

        $eligibility = $this->service->evaluateCheckoutEligibility($user, $plan, $price);

        $this->assertFalse($eligibility->allowed);
        $this->assertSame('BILLING_ONE_TIME_UPGRADE_ONLY', $eligibility->errorCode);
    }

    #[Test]
    public function it_allows_one_time_upgrade_to_higher_tier(): void
    {
        $user = User::factory()->create();

        Order::query()->create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'provider_id' => 'pi_upgrade_'.uniqid(),
            'plan_key' => 'hobbyist',
            'status' => OrderStatus::Paid->value,
            'amount' => 4900,
            'currency' => 'USD',
            'paid_at' => now(),
            'metadata' => [
                'price_key' => 'once',
            ],
        ]);

        $plan = $this->oneTimePlan('indie', 9900);
        $price = $plan->getPrice('once');

        $eligibility = $this->service->evaluateCheckoutEligibility($user, $plan, $price);

        $this->assertTrue($eligibility->allowed);
        $this->assertTrue($eligibility->isUpgrade);
    }

    private function recurringPlan(string $planKey, int $amount): Plan
    {
        return new Plan(
            key: $planKey,
            name: ucfirst($planKey),
            summary: '',
            type: PriceType::Recurring,
            highlight: false,
            features: [],
            entitlements: [],
            prices: [
                'monthly' => new Price(
                    key: 'monthly',
                    label: 'Monthly',
                    amount: $amount,
                    currency: 'USD',
                    interval: 'month',
                    intervalCount: 1,
                    type: PriceType::Recurring,
                    hasTrial: false,
                    trialInterval: null,
                    trialIntervalCount: null,
                    amountIsMinor: true,
                ),
            ],
        );
    }

    private function oneTimePlan(string $planKey, int $amount): Plan
    {
        return new Plan(
            key: $planKey,
            name: ucfirst($planKey),
            summary: '',
            type: PriceType::OneTime,
            highlight: false,
            features: [],
            entitlements: [],
            prices: [
                'once' => new Price(
                    key: 'once',
                    label: 'One-time',
                    amount: $amount,
                    currency: 'USD',
                    interval: 'once',
                    intervalCount: 1,
                    type: PriceType::OneTime,
                    hasTrial: false,
                    trialInterval: null,
                    trialIntervalCount: null,
                    amountIsMinor: true,
                ),
            ],
        );
    }
}
