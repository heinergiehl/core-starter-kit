<?php

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\SocialAccount;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
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
                'provider_name' => $this->resolveProviderName($socialUser),
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
            'provider_name' => $this->resolveProviderName($socialUser),
            'token' => $socialUser->token ?? null,
            'refresh_token' => $socialUser->refreshToken ?? null,
            'expires_at' => $this->resolveExpiry($socialUser),
        ]);

        return $user;
    }

    public function connectToUser(User $user, string $provider, SocialiteUser $socialUser): User
    {
        $providerEnum = \App\Enums\OAuthProvider::from($provider);
        $providerId = (string) $socialUser->getId();

        if ($providerId === '') {
            throw ValidationException::withMessages([
                'social' => __('Unable to resolve provider account id. Please try again.'),
            ]);
        }

        $existingByProviderId = SocialAccount::query()
            ->where('provider', $providerEnum)
            ->where('provider_id', $providerId)
            ->first();

        if ($existingByProviderId && $existingByProviderId->user_id !== $user->id) {
            throw ValidationException::withMessages([
                'social' => __('This social account is already linked to another user.'),
            ]);
        }

        $account = SocialAccount::query()
            ->where('user_id', $user->id)
            ->where('provider', $providerEnum)
            ->first();

        if ($account && $account->provider_id !== $providerId) {
            $conflict = SocialAccount::query()
                ->where('provider', $providerEnum)
                ->where('provider_id', $providerId)
                ->where('user_id', '!=', $user->id)
                ->exists();

            if ($conflict) {
                throw ValidationException::withMessages([
                    'social' => __('This social account is already linked to another user.'),
                ]);
            }
        }

        SocialAccount::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'provider' => $providerEnum,
            ],
            [
                'provider_id' => $providerId,
                'provider_email' => $socialUser->getEmail(),
                'provider_name' => $this->resolveProviderName($socialUser),
                'token' => $socialUser->token ?? null,
                'refresh_token' => $socialUser->refreshToken ?? null,
                'expires_at' => $this->resolveExpiry($socialUser),
            ]
        );

        return $user;
    }

    private function resolveProviderName(SocialiteUser $socialUser): ?string
    {
        $nickname = trim((string) $socialUser->getNickname());

        if ($nickname !== '') {
            return $nickname;
        }

        $name = trim((string) $socialUser->getName());

        return $name === '' ? null : $name;
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
