<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Models\TwoFactorAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class TwoFactorController extends Controller
{
    public function enable(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        if ($user->twoFactorAuth?->isEnabled()) {
            return back()->with('error', __('Two-factor authentication is already enabled.'));
        }

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

        return back()->with('success', __('Two-factor authentication has been enabled.'));
    }

    public function disable(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $request->validate([
            'password' => ['required'],
        ]);

        if (! Hash::check($request->password, $user->password)) {
            return back()->withErrors(['password' => __('The password is incorrect.')]);
        }

        $user->twoFactorAuth?->delete();

        return back()->with('success', __('Two-factor authentication has been disabled.'));
    }
}
