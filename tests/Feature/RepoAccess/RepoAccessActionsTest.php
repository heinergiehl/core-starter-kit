<?php

namespace Tests\Feature\RepoAccess;

use App\Domain\Billing\Models\Order;
use App\Domain\Identity\Models\SocialAccount;
use App\Domain\RepoAccess\Jobs\GrantGitHubRepositoryAccessJob;
use App\Enums\BillingProvider;
use App\Enums\OAuthProvider;
use App\Enums\OrderStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
