<?php

namespace App\Http\Controllers\Auth;

use App\Domain\RepoAccess\Services\RepoAccessService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;
use Throwable;

class SocialAuthController extends Controller
{
    private const CONNECT_USER_SESSION_KEY = 'social_connect_user_id';

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $provider = $this->normalizeProvider($provider);

        $this->prepareConnectMode($request, $provider);
        $this->storeIntendedRedirect($request);

        try {
            return $this->socialiteDriver($provider)->redirect();
        } catch (Throwable $exception) {
            return $this->handleSocialAuthFailure($provider, $exception);
        }
    }

    public function callback(
        string $provider,
        \App\Domain\Identity\Actions\HandleSocialCallback $handler,
        RepoAccessService $repoAccessService
    ): RedirectResponse {
        $provider = $this->normalizeProvider($provider);

        try {
            $socialUser = $this->socialiteDriver($provider)->user();
        } catch (InvalidStateException $exception) {
            if ($this->shouldUseStateless()) {
                try {
                    $socialUser = Socialite::driver($provider)->stateless()->user();
                } catch (Throwable $fallbackException) {
                    return $this->handleSocialAuthFailure($provider, $fallbackException);
                }
            } else {
                report($exception);

                return redirect()
                    ->route('login')
                    ->withErrors(['social' => __('The authentication session expired. Please try again.')]);
            }
        } catch (Throwable $exception) {
            return $this->handleSocialAuthFailure($provider, $exception);
        }

        $connectUserId = session()->pull(self::CONNECT_USER_SESSION_KEY);

        if ($connectUserId) {
            $connectUser = \App\Models\User::query()->find($connectUserId);

            if (! $connectUser) {
                return redirect()
                    ->route('login')
                    ->withErrors(['social' => __('The account linking session expired. Please try again.')]);
            }

            try {
                $user = $handler->connectToUser($connectUser, $provider, $socialUser);
            } catch (ValidationException $exception) {
                return redirect()
                    ->route('profile.edit')
                    ->withErrors($exception->errors());
            } catch (Throwable $exception) {
                report($exception);

                return redirect()
                    ->route('profile.edit')
                    ->withErrors(['social' => __('Unable to connect your :provider account. Please try again.', [
                        'provider' => ucfirst($provider),
                    ])]);
            }

            Auth::login($user, true);

            if ($repoAccessService->isEnabled() && $repoAccessService->hasEligiblePurchase($user)) {
                $repoAccessService->queueGrant($user, 'github_connected');
            }

            return redirect()
                ->intended(route('profile.edit'))
                ->with('success', __('Your :provider account is now connected.', [
                    'provider' => ucfirst($provider),
                ]));
        }

        $email = $socialUser->getEmail();

        if (! $email) {
            return redirect()
                ->route('login')
                ->withErrors(['email' => 'Your social account does not provide an email address.']);
        }

        $user = $handler->execute($provider, $socialUser);

        Auth::login($user, true);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    private function socialiteDriver(string $provider)
    {
        $driver = Socialite::driver($provider);

        if ($provider === 'google') {
            // Prevent silent reuse of the currently active Google identity.
            $driver = $driver->with(['prompt' => 'select_account']);
        }

        return $this->shouldUseStateless() ? $driver->stateless() : $driver;
    }

    private function shouldUseStateless(): bool
    {
        $stateless = config('saas.auth.socialite_stateless');

        if ($stateless === null || $stateless === '') {
            return app()->environment('local');
        }

        return (bool) $stateless;
    }

    private function storeIntendedRedirect(Request $request): void
    {
        $intended = trim((string) $request->query('intended', ''));

        if ($intended === '' || ! $this->isSafeRedirectTarget($intended)) {
            return;
        }

        $request->session()->put('url.intended', $intended);
    }

    private function prepareConnectMode(Request $request, string $provider): void
    {
        if ($provider !== 'github') {
            $request->session()->forget(self::CONNECT_USER_SESSION_KEY);

            return;
        }

        if (! $request->boolean('connect') || ! Auth::check()) {
            $request->session()->forget(self::CONNECT_USER_SESSION_KEY);

            return;
        }

        $request->session()->put(self::CONNECT_USER_SESSION_KEY, (int) Auth::id());
    }

    private function isSafeRedirectTarget(string $target): bool
    {
        if (str_starts_with($target, '/')) {
            return ! str_starts_with($target, '//');
        }

        $host = parse_url($target, PHP_URL_HOST);

        if (! $host) {
            return false;
        }

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);

        return $appHost && strcasecmp($host, $appHost) === 0;
    }

    private function normalizeProvider(string $provider): string
    {
        $provider = strtolower($provider);
        $allowed = array_map('strtolower', config('saas.auth.social_providers', ['google', 'github', 'linkedin']));

        if (! in_array($provider, $allowed, true)) {
            abort(404);
        }

        return $provider;
    }

    private function handleSocialAuthFailure(string $provider, Throwable $exception): RedirectResponse
    {
        report($exception);

        $message = __('Social login via :provider is currently unavailable. Please use email login or contact support.', [
            'provider' => ucfirst($provider),
        ]);

        if (app()->environment('local')) {
            $message .= ' '.$exception->getMessage();
        }

        if (session()->has(self::CONNECT_USER_SESSION_KEY)) {
            session()->forget(self::CONNECT_USER_SESSION_KEY);

            return redirect()
                ->route('profile.edit')
                ->withErrors(['social' => $message]);
        }

        return redirect()
            ->route('login')
            ->withErrors(['social' => $message]);
    }
}
