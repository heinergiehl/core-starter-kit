<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Models\SocialAccount;
use App\Domain\Organization\Services\TeamProvisioner;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirect(string $provider): RedirectResponse
    {
        $provider = $this->normalizeProvider($provider);

        return Socialite::driver($provider)->stateless()->redirect();
    }

    public function callback(string $provider, TeamProvisioner $teams): RedirectResponse
    {
        $provider = $this->normalizeProvider($provider);
        $socialUser = Socialite::driver($provider)->stateless()->user();

        $email = $socialUser->getEmail();

        if (!$email) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Your social account does not provide an email address.']);
        }

        $account = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $socialUser->getId())
            ->first();

        if ($account) {
            $user = $account->user;
            $account->update([
                'provider_email' => $socialUser->getEmail(),
                'provider_name' => $socialUser->getName(),
                'token' => $socialUser->token ?? $account->token,
                'refresh_token' => $socialUser->refreshToken ?? $account->refresh_token,
                'expires_at' => $this->resolveExpiry($socialUser) ?? $account->expires_at,
            ]);
        } else {
            $user = User::query()->where('email', $email)->first();

            if (!$user) {
                $user = User::create([
                    'name' => $socialUser->getName() ?: Str::before($email, '@'),
                    'email' => $email,
                    'password' => Str::random(40),
                ]);
                $user->forceFill(['email_verified_at' => now()])->save();

                $teams->createDefaultTeam($user);
            }

            $account = SocialAccount::query()->create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_id' => $socialUser->getId(),
                'provider_email' => $socialUser->getEmail(),
                'provider_name' => $socialUser->getName(),
                'token' => $socialUser->token ?? null,
                'refresh_token' => $socialUser->refreshToken ?? null,
                'expires_at' => $this->resolveExpiry($socialUser),
            ]);
        }

        Auth::login($user, true);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = strtolower($provider);
        $allowed = array_map('strtolower', config('saas.auth.social_providers', ['google', 'github', 'linkedin']));

        if (!in_array($provider, $allowed, true)) {
            abort(404);
        }

        return $provider;
    }

    private function resolveExpiry(SocialiteUser $user): ?\Illuminate\Support\Carbon
    {
        $expiresIn = $user->expiresIn ?? null;

        if (!$expiresIn) {
            return null;
        }

        return now()->addSeconds((int) $expiresIn);
    }
}
