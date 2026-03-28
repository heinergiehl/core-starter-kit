<?php

namespace Tests\Unit\Domain\Billing;

use App\Domain\Billing\Data\BillingOwner;
use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Billing\Services\BillingAccessService;
use App\Domain\Identity\Models\Account;
use App\Domain\Identity\Models\AccountMembership;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillingAccessServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_returns_an_active_subscription_for_a_billing_owner(): void
    {
        $user = User::factory()->create();

        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active->value,
        ]);

        $subscription = app(BillingAccessService::class)->activeSubscriptionForOwner(BillingOwner::forUser($user));

        $this->assertNotNull($subscription);
        $this->assertSame($user->id, $subscription->user_id);
        $this->assertSame($user->currentAccountId(), $subscription->account_id);
    }

    #[Test]
    public function it_grants_billing_access_for_a_completed_order_without_a_subscription(): void
    {
        $user = User::factory()->create();

        Order::query()->create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'provider_id' => 'pi_test_'.uniqid(),
            'plan_key' => 'starter',
            'status' => OrderStatus::Paid->value,
            'amount' => 4900,
            'currency' => 'USD',
            'paid_at' => now(),
        ]);

        $hasAccess = app(BillingAccessService::class)->hasBillingAccessForOwner(BillingOwner::forUser($user));

        $this->assertTrue($hasAccess);
    }

    #[Test]
    public function it_grants_billing_access_for_account_owners_using_the_underlying_billing_user(): void
    {
        $user = User::factory()->create();
        $account = $user->personalAccount;

        Order::query()->create([
            'user_id' => $user->id,
            'provider' => 'stripe',
            'provider_id' => 'pi_test_account_'.uniqid(),
            'plan_key' => 'starter',
            'status' => OrderStatus::Paid->value,
            'amount' => 4900,
            'currency' => 'USD',
            'paid_at' => now(),
        ]);

        $owner = BillingOwner::forAccount($account, $user->id, $user);
        $hasAccess = app(BillingAccessService::class)->hasBillingAccessForOwner($owner);

        $this->assertTrue($hasAccess);
    }

    #[Test]
    public function it_ignores_active_subscriptions_from_non_current_accounts_when_resolving_for_a_user(): void
    {
        $user = User::factory()->create();
        $secondaryAccount = Account::factory()->create();

        AccountMembership::factory()->create([
            'account_id' => $secondaryAccount->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);

        Subscription::factory()->create([
            'user_id' => $user->id,
            'account_id' => $secondaryAccount->id,
            'status' => SubscriptionStatus::Active->value,
        ]);

        $subscription = app(BillingAccessService::class)->activeSubscriptionForUser($user);

        $this->assertNull($subscription);
    }
}
