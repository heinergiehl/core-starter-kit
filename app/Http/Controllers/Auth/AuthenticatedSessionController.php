<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        if (app()->environment('local')) {
            Log::info('login_controller_attempt', [
                'ip' => $request->ip(),
            ]);
        }

        try {
            $request->authenticate();
        } catch (\Throwable $exception) {
            Log::warning('login_controller_failed', [
                'ip' => $request->ip(),
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $user = Auth::user();
        if (app()->environment('local')) {
            Log::info('login_controller_authenticated', [
                'user_id' => $user?->id,
            ]);
        }

        // Check if 2FA is enabled for this user
        if ($user->hasTwoFactorEnabled()) {
            // Store user ID in session and logout temporarily
            $request->session()->put('2fa_user_id', $user->id);
            $request->session()->put('2fa_remember', $request->boolean('remember'));

            Auth::guard('web')->logout();

            $request->session()->regenerate();
            $request->session()->regenerateToken();

            if (app()->environment('local')) {
                Log::info('login_controller_requires_2fa', [
                    'user_id' => $user->id,
                ]);
            }

            return redirect()->route('two-factor.challenge');
        }

        $request->session()->regenerate();

        // Determine redirect destination based on user role
        $redirectTo = $user->is_admin
            ? route('filament.admin.pages.dashboard', absolute: false)
            : route('dashboard', absolute: false);

        if (app()->environment('local')) {
            Log::info('login_controller_redirect', [
                'user_id' => $user?->id,
                'is_admin' => $user?->is_admin,
                'redirect' => $redirectTo,
            ]);
        }

        return redirect()->intended($redirectTo);
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
