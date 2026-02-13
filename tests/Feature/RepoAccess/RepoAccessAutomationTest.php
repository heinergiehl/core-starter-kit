<?php

namespace Tests\Feature\RepoAccess;

use App\Domain\Billing\Events\Subscription\SubscriptionStarted;
use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Identity\Models\SocialAccount;
use App\Domain\RepoAccess\Services\GitHubRepositoryAccessService;
use App\Domain\RepoAccess\Services\RepoAccessService;
use App\Enums\BillingProvider;
use App\Enums\OAuthProvider;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
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

    public function test_owner_collaborator_validation_error_is_stored_as_friendly_message(): void
    {
        $user = User::factory()->create();
        app(RepoAccessService::class)->setGitHubUsername($user, 'acme', 1, 'test');

        Http::fake([
            'https://api.github.com/repos/acme/saas-kit-private/collaborators/acme' => Http::response([
                'message' => 'Validation Failed',
                'errors' => [
                    [
                        'resource' => 'Repository',
                        'code' => 'custom',
                        'message' => 'Repository owner cannot be a collaborator.',
                    ],
                ],
            ], 422),
        ]);

        app(GitHubRepositoryAccessService::class)->grantReadAccess($user, 'test');

        $this->assertDatabaseHas('repo_access_grants', [
            'user_id' => $user->id,
            'provider' => 'github',
            'repository_owner' => 'acme',
            'repository_name' => 'saas-kit-private',
            'status' => 'failed',
            'last_error' => 'The selected username owns acme/saas-kit-private and already has access. Choose the customer GitHub account instead.',
        ]);
    }

    public function test_connection_errors_are_stored_as_friendly_message(): void
    {
        $user = User::factory()->create();
        app(RepoAccessService::class)->setGitHubUsername($user, 'octocat', 1, 'test');

        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out after 15001 milliseconds'));

        try {
            app(GitHubRepositoryAccessService::class)->grantReadAccess($user, 'test');
            $this->fail('Expected repository grant to throw on connection failure.');
        } catch (ConnectionException) {
            // Expected. The job should retry while the persisted grant keeps a user-safe error.
        }

        $this->assertDatabaseHas('repo_access_grants', [
            'user_id' => $user->id,
            'provider' => 'github',
            'repository_owner' => 'acme',
            'repository_name' => 'saas-kit-private',
            'status' => 'failed',
            'last_error' => 'GitHub API could not be reached. Please try again in a moment.',
        ]);
    }
}
