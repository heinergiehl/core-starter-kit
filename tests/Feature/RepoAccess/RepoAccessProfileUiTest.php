<?php

namespace Tests\Feature\RepoAccess;

use App\Domain\Identity\Models\SocialAccount;
use App\Domain\RepoAccess\Models\RepoAccessGrant;
use App\Enums\OAuthProvider;
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

    public function test_profile_shows_connect_button_when_github_is_not_connected(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertSee('Connect GitHub');
        $response->assertSee('Not connected');
    }

    public function test_profile_shows_connected_account_and_grant_status(): void
    {
        $user = User::factory()->create();

        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => OAuthProvider::GitHub,
            'provider_id' => '12345',
            'provider_email' => $user->email,
            'provider_name' => 'octocat',
        ]);

        RepoAccessGrant::query()->create([
            'user_id' => $user->id,
            'provider' => 'github',
            'repository_owner' => 'acme',
            'repository_name' => 'saas-kit-private',
            'github_username' => 'octocat',
            'status' => RepoAccessGrantStatus::Granted,
            'granted_at' => now(),
        ]);

        $response = $this->actingAs($user)->get('/profile');

        $response->assertOk();
        $response->assertSee('@octocat');
        $response->assertSee('Access Granted');
        $response->assertSee('Switch GitHub Account');
    }
}
