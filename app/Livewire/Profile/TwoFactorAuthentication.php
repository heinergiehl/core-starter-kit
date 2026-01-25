<?php

namespace App\Livewire\Profile;

use App\Domain\Identity\Models\TwoFactorAuth;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class TwoFactorAuthentication extends Component
{
    public string $code = '';

    public string $password = '';

    public string $disablePassword = '';

    public string $regeneratePassword = '';

    public bool $showBackupCodes = false;

    public function enable(): void
    {
        $user = Auth::user();

        if ($user->twoFactorAuth?->isEnabled()) {
            $this->dispatch('notify', type: 'error', message: __('Two-factor authentication is already enabled.'));

            return;
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

        $this->dispatch('notify', type: 'info', message: __('Scan the QR code with your authenticator app.'));
    }

    public function confirm(): void
    {
        $this->validate([
            'code' => ['required', 'string', 'size:6'],
        ]);

        $user = Auth::user();
        $twoFactor = $user->twoFactorAuth;

        if (! $twoFactor || $twoFactor->isEnabled()) {
            $this->dispatch('notify', type: 'error', message: __('Invalid request.'));

            return;
        }

        if (! $twoFactor->verify($this->code)) {
            $this->addError('code', __('The provided code is invalid.'));

            return;
        }

        $twoFactor->update(['confirmed_at' => now()]);

        $this->code = '';
        $this->showBackupCodes = true;

        $this->dispatch('notify', type: 'success', message: __('Two-factor authentication has been enabled!'));
    }

    public function disable(): void
    {
        $this->validate([
            'disablePassword' => ['required'],
        ]);

        $user = Auth::user();

        if (! Hash::check($this->disablePassword, $user->password)) {
            $this->addError('disablePassword', __('The password is incorrect.'));

            return;
        }

        $user->twoFactorAuth?->delete();

        $this->disablePassword = '';

        $this->dispatch('notify', type: 'success', message: __('Two-factor authentication has been disabled.'));
    }

    public function regenerateBackupCodes(): void
    {
        $this->validate([
            'regeneratePassword' => ['required'],
        ]);

        $user = Auth::user();

        if (! Hash::check($this->regeneratePassword, $user->password)) {
            $this->addError('regeneratePassword', __('The password is incorrect.'));

            return;
        }

        $twoFactor = $user->twoFactorAuth;

        if (! $twoFactor?->isEnabled()) {
            $this->dispatch('notify', type: 'error', message: __('Two-factor authentication is not enabled.'));

            return;
        }

        $twoFactor->update([
            'backup_codes' => TwoFactorAuth::generateBackupCodes(),
        ]);

        $this->regeneratePassword = '';
        $this->showBackupCodes = true;

        $this->dispatch('notify', type: 'success', message: __('Backup codes have been regenerated.'));
    }

    public function render()
    {
        $user = Auth::user();
        $twoFactor = $user->twoFactorAuth;

        return view('livewire.profile.two-factor-authentication', [
            'user' => $user,
            'twoFactor' => $twoFactor,
            'isEnabled' => $twoFactor?->isEnabled() ?? false,
            'isPending' => $twoFactor && ! $twoFactor->isEnabled(),
        ]);
    }
}
