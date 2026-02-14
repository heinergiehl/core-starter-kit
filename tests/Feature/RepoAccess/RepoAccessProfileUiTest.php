<?php

namespace Tests\Feature\RepoAccess;

use App\Domain\Billing\Models\Order;
use App\Domain\Identity\Models\SocialAccount;
use App\Domain\RepoAccess\Models\RepoAccessGrant;
use App\Enums\BillingProvider;
use App\Enums\OAuthProvider;
use App\Enums\OrderStatus;
use App\Enums\RepoAccessGrantStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RepoAccessProfileUiTest extends TestCase
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

    public function test_profile_shows_username_search_when_no_github_username_selected(): void
    {
        $user = User::factory()->create();

        Order::query()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe,
            'provider_id' => 'pi_profile_1',
            'plan_key' => 'lifetime',
            'status' => OrderStatus::Paid,
            'amount' => 4900,
            'currency' => 'USD',
            'metadata' => [],
        ]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertSee('Find GitHub account');
        $response->assertSee('Not selected');
    }

    public function test_profile_shows_selected_username_and_grant_status(): void
    {
        $user = User::factory()->create();

        Order::query()->create([
            'user_id' => $user->id,
            'provider' => BillingProvider::Stripe,
            'provider_id' => 'pi_profile_2',
            'plan_key' => 'lifetime',
            'status' => OrderStatus::Paid,
            'amount' => 4900,
            'currency' => 'USD',
            'metadata' => [],
        ]);

        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => OAuthProvider::GitHub,
            'provider_id' => '12345',
            'provider_email' => $user->email,
            'provider_name' => 'octocat',
        ]);

        RepoAccessGrant::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'provider' => 'github',
                'repository_owner' => 'acme',
                'repository_name' => 'saas-kit-private',
            ],
            [
                'github_username' => 'octocat',
                'status' => RepoAccessGrantStatus::Granted,
                'granted_at' => now(),
            ]
        );

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertSee('@octocat');
        $response->assertSee('Access Granted');
        $response->assertSee('Refresh Status');
    }
}
