<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Identity\Models\TwoFactorAuth;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\View\View;
use PragmaRX\Google2FA\Google2FA;

/**
 * Two-Factor Authentication controller.
 *
 * Handles enabling, confirming, disabling, and challenging 2FA.
 */
class TwoFactorController extends Controller
{
    /**
     * Show 2FA setup page.
     */
    public function show(Request $request): View
    {
        $user = $request->user();
        $twoFactor = $user->twoFactorAuth;
        
        $qrCodeUri = null;
        $backupCodes = null;
        
        if ($twoFactor && !$twoFactor->isEnabled()) {
            // Show QR code for pending setup
            $qrCodeUri = $twoFactor->getQrCodeUri(
                $user->email,
                config('app.name', 'SaaS Kit')
            );
        }
        
        if ($twoFactor && $twoFactor->isEnabled()) {
            // Show backup codes if just enabled
            if (session('show_backup_codes')) {
                $backupCodes = $twoFactor->backup_codes;
            }
        }

        return view('profile.partials.two-factor-form', [
            'user' => $user,
            'twoFactor' => $twoFactor,
            'qrCodeUri' => $qrCodeUri,
            'backupCodes' => $backupCodes,
        ]);
    }

    /**
     * Enable 2FA (step 1: generate secret).
     */
    public function enable(Request $request): RedirectResponse
    {
        $user = $request->user();
        
        // Don't allow if already enabled
        if ($user->twoFactorAuth?->isEnabled()) {
            return back()->with('error', __('Two-factor authentication is already enabled.'));
        }

        // Generate new secret
        $secret = TwoFactorAuth::generateSecret();
        $backupCodes = TwoFactorAuth::generateBackupCodes();

        TwoFactorAuth::updateOrCreate(
            ['user_id' => $user->id],
            [
                'secret' => Crypt::encryptString($secret),
                'backup_codes' => $backupCodes,
                'enabled_at' => now(),
                'confirmed_at' => null,
            ]
        );

        return back()->with('status', __('Scan the QR code with your authenticator app and enter the code to confirm.'));
    }

    /**
     * Confirm 2FA (step 2: verify code).
     */
    public function confirm(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = $request->user();
        $twoFactor = $user->twoFactorAuth;

        if (!$twoFactor || $twoFactor->isEnabled()) {
            return back()->with('error', __('Invalid request.'));
        }

        if (!$twoFactor->verify($request->code)) {
            return back()->withErrors(['code' => __('The provided code is invalid.')]);
        }

        $twoFactor->update(['confirmed_at' => now()]);

        return back()
            ->with('status', __('Two-factor authentication has been enabled.'))
            ->with('show_backup_codes', true);
    }

    /**
     * Disable 2FA.
     */
    public function disable(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        $user->twoFactorAuth?->delete();

        return back()->with('status', __('Two-factor authentication has been disabled.'));
    }

    /**
     * Show 2FA challenge during login.
     */
    public function showChallenge(): View|RedirectResponse
    {
        // Ensure there's a pending 2FA challenge
        if (!session('2fa_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.two-factor-challenge');
    }

    /**
     * Verify 2FA challenge.
     */
    public function verifyChallenge(Request $request): RedirectResponse
    {
        $request->validate([
            'code' => ['required_without:recovery_code', 'nullable', 'string'],
            'recovery_code' => ['required_without:code', 'nullable', 'string'],
        ]);

        $userId = session('2fa_user_id');
        
        if (!$userId) {
            return redirect()->route('login');
        }

        $user = \App\Models\User::find($userId);
        $twoFactor = $user?->twoFactorAuth;

        if (!$user || !$twoFactor?->isEnabled()) {
            return redirect()->route('login');
        }

        $valid = false;

        if ($request->filled('code')) {
            $valid = $twoFactor->verify($request->code);
        } elseif ($request->filled('recovery_code')) {
            $valid = $twoFactor->verifyBackupCode($request->recovery_code);
        }

        if (!$valid) {
            return back()->withErrors(['code' => __('The provided code is invalid.')]);
        }

        // Clear challenge session and log in
        session()->forget('2fa_user_id');
        auth()->login($user, session()->pull('2fa_remember', false));

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Regenerate backup codes.
     */
    public function regenerateBackupCodes(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();
        $twoFactor = $user->twoFactorAuth;

        if (!$twoFactor?->isEnabled()) {
            return back()->with('error', __('Two-factor authentication is not enabled.'));
        }

        $twoFactor->update([
            'backup_codes' => TwoFactorAuth::generateBackupCodes(),
        ]);

        return back()
            ->with('status', __('Backup codes have been regenerated.'))
            ->with('show_backup_codes', true);
    }
}
