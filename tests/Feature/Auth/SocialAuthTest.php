<?php

namespace Tests\Feature\Auth;

use App\Domain\Identity\Models\SocialAccount;
use App\Enums\OAuthProvider;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SocialAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_auth_creates_user_with_random_password(): void
    {
        // Mock Socialite User
        $socialUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
        $socialUser->shouldReceive('getId')->andReturn('123456');
        $socialUser->shouldReceive('getName')->andReturn('Test User');
        $socialUser->shouldReceive('getNickname')->andReturn(null);
        $socialUser->shouldReceive('getEmail')->andReturn('test@example.com');
        $socialUser->shouldReceive('getAvatar')->andReturn('https://example.com/avatar.jpg');
        $socialUser->token = 'test-token';
        $socialUser->refreshToken = 'test-refresh-token';
        $socialUser->expiresIn = 3600;

        // Mock Socialite Provider
        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $provider->shouldReceive('user')->andReturn($socialUser);

        // Mock Socialite Factory
        Socialite::shouldReceive('driver')->with('github')->andReturn($provider);

        // Mock Config for stateless check if needed, but default relies on config or local env
        // The controller checks config('saas.auth.socialite_stateless')

        $response = $this->get('/auth/github/callback');

        $response->assertRedirect(route('dashboard', absolute: false));

        $this->assertDatabaseHas('users', [
            'email' => 'test@example.com',
            'name' => 'Test User',
        ]);

        $user = User::where('email', 'test@example.com')->first();
        $this->assertNotNull($user->password);
        $this->assertNotEmpty($user->password);
    }

    public function test_social_auth_callback_gracefully_handles_provider_configuration_errors(): void
    {
        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $provider->shouldReceive('user')
            ->once()
            ->andThrow(new RuntimeException('Missing required config keys: client_id, client_secret'));

        Socialite::shouldReceive('driver')->with('github')->andReturn($provider);

        $response = $this->get('/auth/github/callback');

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['social']);
    }

    public function test_google_redirect_prompts_for_account_selection(): void
    {
        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $provider->shouldReceive('with')
            ->once()
            ->with(['prompt' => 'select_account'])
            ->andReturnSelf();
        $provider->shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('/oauth/google'));

        Socialite::shouldReceive('driver')->with('google')->andReturn($provider);

        $response = $this->get('/auth/google/redirect');

        $response->assertRedirect('/oauth/google');
    }

    public function test_authenticated_user_can_connect_github_account_without_email_in_connect_mode(): void
    {
        $user = User::factory()->create();

        $socialUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
        $socialUser->shouldReceive('getId')->andReturn('github-987');
        $socialUser->shouldReceive('getName')->andReturn('GitHub User');
        $socialUser->shouldReceive('getNickname')->andReturn('octocat');
        $socialUser->shouldReceive('getEmail')->andReturn(null);
        $socialUser->token = 'test-token';
        $socialUser->refreshToken = 'test-refresh-token';
        $socialUser->expiresIn = 3600;

        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $provider->shouldReceive('user')->andReturn($socialUser);

        Socialite::shouldReceive('driver')->with('github')->andReturn($provider);

        $response = $this
            ->actingAs($user)
            ->withSession(['social_connect_user_id' => $user->id])
            ->get('/auth/github/callback');

        $response->assertRedirect(route('profile.edit', [], false));

        $this->assertDatabaseHas('social_accounts', [
            'user_id' => $user->id,
            'provider' => OAuthProvider::GitHub->value,
            'provider_id' => 'github-987',
            'provider_name' => 'octocat',
        ]);
    }

    public function test_connect_mode_rejects_github_account_already_linked_to_another_user(): void
    {
        $currentUser = User::factory()->create();
        $otherUser = User::factory()->create();

        SocialAccount::query()->create([
            'user_id' => $otherUser->id,
            'provider' => OAuthProvider::GitHub,
            'provider_id' => 'shared-github-id',
            'provider_email' => 'other@example.com',
            'provider_name' => 'other-account',
        ]);

        $socialUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
        $socialUser->shouldReceive('getId')->andReturn('shared-github-id');
        $socialUser->shouldReceive('getName')->andReturn('Other Account');
        $socialUser->shouldReceive('getNickname')->andReturn('other-account');
        $socialUser->shouldReceive('getEmail')->andReturn('other@example.com');
        $socialUser->token = 'test-token';
        $socialUser->refreshToken = 'test-refresh-token';
        $socialUser->expiresIn = 3600;

        $provider = Mockery::mock(\Laravel\Socialite\Contracts\Provider::class);
        $provider->shouldReceive('user')->andReturn($socialUser);

        Socialite::shouldReceive('driver')->with('github')->andReturn($provider);

        $response = $this
            ->actingAs($currentUser)
            ->withSession(['social_connect_user_id' => $currentUser->id])
            ->get('/auth/github/callback');

        $response->assertRedirect(route('profile.edit', [], false));
        $response->assertSessionHasErrors(['social']);
    }
}
