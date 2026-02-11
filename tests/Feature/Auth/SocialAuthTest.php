<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;
use RuntimeException;

class SocialAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_social_auth_creates_user_with_random_password(): void
    {
        // Mock Socialite User
        $socialUser = Mockery::mock(\Laravel\Socialite\Two\User::class);
        $socialUser->shouldReceive('getId')->andReturn('123456');
        $socialUser->shouldReceive('getName')->andReturn('Test User');
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
}
