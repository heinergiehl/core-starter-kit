<?php

namespace Tests\Feature\RepoAccess;

use App\Domain\Billing\Models\Order;
use App\Domain\Billing\Models\Subscription;
use App\Domain\Identity\Models\SocialAccount;
use App\Domain\RepoAccess\Jobs\GrantGitHubRepositoryAccessJob;
use App\Enums\BillingProvider;
use App\Enums\OAuthProvider;
use App\Enums\OrderStatus;
use App\Enums\SubscriptionStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class RepoAccessActionsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('repo_access.enabled', true);
        config()->set('repo_access.provider', 'github');
        config()->set('repo_access.github.owner', 'acme');
        config()->set('repo_access.github.repository', 'saas-kit-private');
    }

    public function test_manual_repo_access_sync_requires_successful_purchase(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->from('/profile')->post('/repo-access/sync');

        $response->assertRedirect('/profile');
        $response->assertSessionHas('error');
    }

    public function test_manual_repo_access_sync_dispatches_job_when_user_is_eligible(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => OAuthProvider::GitHub,
            'provider_id' => '12345',
            'provider_email' => $user->email,
            'provider_name' => 'octocat',
        ]);

        Order::query()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe,
            'provider_id' => 'pi_sync_123',
            'plan_key' => 'lifetime',
            'status' => OrderStatus::Paid,
            'amount' => 4900,
            'currency' => 'USD',
            'metadata' => [],
        ]);

        $response = $this->actingAs($user)->from('/profile')->post('/repo-access/sync');

        $response->assertRedirect('/profile');
        $response->assertSessionHas('success');

        Queue::assertPushed(GrantGitHubRepositoryAccessJob::class, function ($job) use ($user) {
            return $job->userId === $user->id && $job->source === 'manual_sync';
        });

        $this->assertDatabaseHas('repo_access_grants', [
            'user_id' => $user->id,
            'provider' => 'github',
            'repository_owner' => 'acme',
            'repository_name' => 'saas-kit-private',
            'status' => 'queued',
        ]);
    }

    public function test_manual_repo_access_sync_requires_linked_github_account(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
        ]);

        $response = $this->actingAs($user)->from('/profile')->post('/repo-access/sync');

        $response->assertRedirect('/profile');
        $response->assertSessionHas('error');
        Queue::assertNothingPushed();
    }

    public function test_repo_access_status_endpoint_returns_realtime_state(): void
    {
        $user = User::factory()->create();

        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => OAuthProvider::GitHub,
            'provider_id' => '67890',
            'provider_email' => $user->email,
            'provider_name' => 'octocat',
        ]);

        Order::query()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe,
            'provider_id' => 'pi_status_123',
            'plan_key' => 'lifetime',
            'status' => OrderStatus::Paid,
            'amount' => 4900,
            'currency' => 'USD',
            'metadata' => [],
        ]);

        $response = $this->actingAs($user)
            ->getJson('/repo-access/status');

        $response->assertOk()
            ->assertJson([
                'enabled' => true,
                'eligible' => true,
                'github_connected' => true,
                'github_username' => 'octocat',
                'repository' => 'acme/saas-kit-private',
            ]);
    }

    public function test_manual_repo_access_sync_returns_json_when_requested(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => OAuthProvider::GitHub,
            'provider_id' => '22222',
            'provider_email' => $user->email,
            'provider_name' => 'octocat',
        ]);

        Order::query()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe,
            'provider_id' => 'pi_sync_789',
            'plan_key' => 'lifetime',
            'status' => OrderStatus::Paid,
            'amount' => 4900,
            'currency' => 'USD',
            'metadata' => [],
        ]);

        $response = $this->actingAs($user)
            ->postJson('/repo-access/sync');

        $response->assertStatus(202)
            ->assertJson([
                'status' => 'queued',
            ]);
    }

    public function test_disconnect_github_removes_social_account_and_sets_waiting_status(): void
    {
        $user = User::factory()->create();

        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => OAuthProvider::GitHub,
            'provider_id' => '12345',
            'provider_email' => $user->email,
            'provider_name' => 'octocat',
        ]);

        $response = $this->actingAs($user)->from('/profile')->delete('/repo-access/github');

        $response->assertRedirect('/profile');
        $response->assertSessionHas('success');

        $this->assertDatabaseMissing('social_accounts', [
            'user_id' => $user->id,
            'provider' => OAuthProvider::GitHub->value,
            'provider_id' => '12345',
        ]);

        $this->assertDatabaseHas('repo_access_grants', [
            'user_id' => $user->id,
            'provider' => 'github',
            'repository_owner' => 'acme',
            'repository_name' => 'saas-kit-private',
            'status' => 'awaiting_github_link',
        ]);
    }

    public function test_github_search_endpoint_returns_matches_for_eligible_user(): void
    {
        $user = User::factory()->create();

        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
        ]);

        Http::fake([
            'https://api.github.com/search/users*' => Http::response([
                'items' => [
                    [
                        'login' => 'octocat',
                        'id' => 1,
                        'avatar_url' => 'https://avatars.githubusercontent.com/u/1',
                        'html_url' => 'https://github.com/octocat',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($user)->getJson('/repo-access/github/search?q=octo');

        $response->assertOk()
            ->assertJson([
                'items' => [
                    [
                        'login' => 'octocat',
                        'id' => 1,
                    ],
                ],
            ]);
    }

    public function test_selecting_username_allows_sync_without_social_oauth_link(): void
    {
        Queue::fake();

        $user = User::factory()->create();

        Subscription::factory()->create([
            'user_id' => $user->id,
            'status' => SubscriptionStatus::Active,
        ]);

        Http::fake([
            'https://api.github.com/users/octocat' => Http::response([
                'login' => 'octocat',
                'id' => 1,
                'avatar_url' => 'https://avatars.githubusercontent.com/u/1',
                'html_url' => 'https://github.com/octocat',
            ], 200),
        ]);

        $selectResponse = $this->actingAs($user)->postJson('/repo-access/github/select', [
            'login' => 'octocat',
        ]);

        $selectResponse->assertOk()
            ->assertJsonPath('user.login', 'octocat');

        $this->assertDatabaseHas('repo_access_grants', [
            'user_id' => $user->id,
            'provider' => 'github',
            'repository_owner' => 'acme',
            'repository_name' => 'saas-kit-private',
            'github_username' => 'octocat',
            'status' => 'awaiting_github_link',
        ]);

        $syncResponse = $this->actingAs($user)->postJson('/repo-access/sync');

        $syncResponse->assertStatus(202)
            ->assertJson([
                'status' => 'queued',
            ]);

        Queue::assertPushed(GrantGitHubRepositoryAccessJob::class, function ($job) use ($user) {
            return $job->userId === $user->id;
        });
    }
}
