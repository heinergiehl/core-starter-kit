<?php

namespace Tests\Feature\RepoAccess;

use App\Domain\Billing\Events\Subscription\SubscriptionStarted;
use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Identity\Models\SocialAccount;
use App\Enums\BillingProvider;
use App\Enums\OAuthProvider;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class RepoAccessAutomationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();

        config()->set('repo_access.enabled', true);
        config()->set('repo_access.provider', 'github');
        config()->set('repo_access.github.token', 'ghp_test_token');
        config()->set('repo_access.github.owner', 'acme');
        config()->set('repo_access.github.repository', 'saas-kit-private');
        config()->set('repo_access.github.api_url', 'https://api.github.com');
        config()->set('repo_access.github.permission', 'pull');
    }

    public function test_subscription_started_grants_github_repository_access(): void
    {
        $user = User::factory()->create();

        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => OAuthProvider::GitHub,
            'provider_id' => '12345',
            'provider_email' => $user->email,
            'provider_name' => 'Buyer Name',
        ]);

        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
        ]);

        Http::fake([
            'https://api.github.com/user/12345' => Http::response(['login' => 'octocat'], 200),
            'https://api.github.com/repos/acme/saas-kit-private/collaborators/octocat' => Http::response([], 201),
        ]);

        event(new SubscriptionStarted($subscription, 4900, 'USD'));

        Http::assertSentCount(2);
        Http::assertSent(function (Request $request): bool {
            return $request->method() === 'PUT'
                && $request->url() === 'https://api.github.com/repos/acme/saas-kit-private/collaborators/octocat'
                && $request['permission'] === 'pull';
        });
    }

    public function test_paid_one_time_order_transition_grants_access_once(): void
    {
        $user = User::factory()->create();

        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => OAuthProvider::GitHub,
            'provider_id' => '9876',
            'provider_email' => $user->email,
            'provider_name' => 'Buyer Name',
        ]);

        Http::fake([
            'https://api.github.com/user/9876' => Http::response(['login' => 'buyerlogin'], 200),
            'https://api.github.com/repos/acme/saas-kit-private/collaborators/buyerlogin' => Http::response([], 201),
        ]);

        $order = Order::query()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe,
            'provider_id' => 'pi_123',
            'plan_key' => 'lifetime',
            'status' => OrderStatus::Pending,
            'amount' => 4900,
            'currency' => 'USD',
            'metadata' => [],
        ]);

        Http::assertNothingSent();

        $order->update(['status' => OrderStatus::Paid]);

        Http::assertSentCount(2);

        // No additional grant call when status stays paid.
        $order->update(['status' => OrderStatus::Paid]);
        Http::assertSentCount(2);
    }

    public function test_module_noops_when_disabled_or_without_github_link(): void
    {
        $user = User::factory()->create();
        $subscription = Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
        ]);

        Http::fake();

        config()->set('repo_access.enabled', false);
        event(new SubscriptionStarted($subscription, 2900, 'USD'));
        Http::assertNothingSent();

        config()->set('repo_access.enabled', true);
        event(new SubscriptionStarted($subscription, 2900, 'USD'));
        Http::assertNothingSent();
    }
}
