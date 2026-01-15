<?php

namespace App\Livewire\Auth;

use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

class Login extends Component
{
    public string $email = '';
    public string $password = '';
    public bool $remember = false;

    public function login(): void
    {
        Log::channel('auth')->info('login_attempt', [
            'email' => $this->email,
            'ip' => request()->ip(),
            'user_agent' => (string) request()->userAgent(),
        ]);

        $this->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $this->ensureIsNotRateLimited();

        if (!Auth::attempt(['email' => $this->email, 'password' => $this->password], $this->remember)) {
            RateLimiter::hit($this->throttleKey());

            Log::channel('auth')->warning('login_failed', [
                'email' => $this->email,
                'ip' => request()->ip(),
            ]);

            $this->addError('email', __('auth.failed'));
            $this->dispatch('login-failed');
            return;
        }

        RateLimiter::clear($this->throttleKey());

        $user = Auth::user();

        Log::channel('auth')->info('login_success', [
            'user_id' => $user?->id,
            'email' => $user?->email,
            'session_id' => session()->getId(),
        ]);

        // Check if 2FA is enabled for this user
        if ($user->hasTwoFactorEnabled()) {
            session()->put('2fa_user_id', $user->id);
            session()->put('2fa_remember', $this->remember);
            
            Auth::guard('web')->logout();
            session()->regenerateToken();

            Log::channel('auth')->info('login_requires_2fa', [
                'user_id' => $user->id,
                'email' => $user->email,
            ]);

            $this->redirect(route('two-factor.challenge'));
            return;
        }

        session()->regenerate();

        Log::channel('auth')->info('login_redirect', [
            'user_id' => $user->id,
            'email' => $user->email,
            'session_id' => session()->getId(),
            'redirect' => route('dashboard'),
        ]);

        $this->redirect(route('dashboard'));
    }

    protected function ensureIsNotRateLimited(): void
    {
        if (!RateLimiter::tooManyAttempts($this->throttleKey(), 5)) {
            return;
        }

        event(new Lockout(request()));

        $seconds = RateLimiter::availableIn($this->throttleKey());

        Log::channel('auth')->warning('login_rate_limited', [
            'email' => $this->email,
            'ip' => request()->ip(),
            'seconds' => $seconds,
        ]);

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function throttleKey(): string
    {
        return Str::transliterate(Str::lower($this->email) . '|' . request()->ip());
    }

    public function render()
    {
        return view('livewire.auth.login')
            ->layout('layouts.guest');
    }
}
