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
        Log::info('login_controller_attempt', [
            'email' => $request->string('email'),
            'ip' => $request->ip(),
            'session_id' => $request->session()->getId(),
            'session_driver' => config('session.driver'),
            'session_domain' => config('session.domain'),
        ]);

        try {
            $request->authenticate();
        } catch (\Throwable $exception) {
            Log::warning('login_controller_failed', [
                'email' => $request->string('email'),
                'ip' => $request->ip(),
                'error' => $exception->getMessage(),
                'session_id' => $request->session()->getId(),
            ]);

            throw $exception;
        }

        $user = Auth::user();
        Log::info('login_controller_authenticated', [
            'user_id' => $user?->id,
            'email' => $user?->email,
            'session_id' => $request->session()->getId(),
        ]);

        // Check if 2FA is enabled for this user
        if ($user->hasTwoFactorEnabled()) {
            // Store user ID in session and logout temporarily
            $request->session()->put('2fa_user_id', $user->id);
            $request->session()->put('2fa_remember', $request->boolean('remember'));
            
            Auth::guard('web')->logout();
            
            // Don't invalidate session - we need the 2fa_user_id
            $request->session()->regenerateToken();

            Log::info('login_controller_requires_2fa', [
                'user_id' => $user->id,
                'email' => $user->email,
                'session_id' => $request->session()->getId(),
            ]);

            return redirect()->route('two-factor.challenge');
        }

        $request->session()->regenerate();

        // Determine redirect destination based on user role
        $redirectTo = $user->is_admin 
            ? route('filament.admin.pages.dashboard', absolute: false)
            : route('dashboard', absolute: false);

        Log::info('login_controller_redirect', [
            'user_id' => $user?->id,
            'email' => $user?->email,
            'is_admin' => $user?->is_admin,
            'session_id' => $request->session()->getId(),
            'redirect' => $redirectTo,
        ]);

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
