<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class HandleSocialCallback
{
    /**
     * Handle the social callback logic: find/link account or create user.
     */
    public function execute(string $provider, SocialiteUser $socialUser): User
    {
        $providerEnum = \App\Enums\OAuthProvider::from($provider);

        $account = SocialAccount::query()
            ->where('provider', $providerEnum)
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

            return $user;
        }

        $email = $socialUser->getEmail();
        $user = User::query()->where('email', $email)->first();

        if (! $user) {
            $user = User::create([
                'name' => $socialUser->getName() ?: Str::before($email, '@'),
                'email' => $email,
                'password' => Hash::make(Str::random(32)), // Generate random secure password
            ]);
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        SocialAccount::query()->create([
            'user_id' => $user->id,
            'provider' => $providerEnum,
            'provider_id' => $socialUser->getId(),
            'provider_email' => $socialUser->getEmail(),
            'provider_name' => $socialUser->getName(),
            'token' => $socialUser->token ?? null,
            'refresh_token' => $socialUser->refreshToken ?? null,
            'expires_at' => $this->resolveExpiry($socialUser),
        ]);

        return $user;
    }

    private function resolveExpiry(SocialiteUser $user): ?\Illuminate\Support\Carbon
    {
        $expiresIn = $user->expiresIn ?? null;

        if (! $expiresIn) {
            return null;
        }

        return now()->addSeconds((int) $expiresIn);
    }
}
