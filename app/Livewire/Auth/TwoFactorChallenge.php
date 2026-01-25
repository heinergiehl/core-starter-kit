<?php

namespace App\Livewire\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TwoFactorChallenge extends Component
{
    public string $code = '';

    public string $recovery_code = '';

    public bool $useRecoveryCode = false;

    public function mount(): void
    {
        if (! session('2fa_user_id')) {
            $this->redirect(route('login'));
        }
    }

    public function toggleRecoveryCode(): void
    {
        $this->useRecoveryCode = ! $this->useRecoveryCode;
        $this->code = '';
        $this->recovery_code = '';
        $this->resetErrorBag();
    }

    public function verify(): void
    {
        if ($this->useRecoveryCode) {
            $this->validate([
                'recovery_code' => ['required', 'string'],
            ]);
        } else {
            $this->validate([
                'code' => ['required', 'string', 'size:6'],
            ]);
        }

        $userId = session('2fa_user_id');
        $user = User::find($userId);
        $twoFactor = $user?->twoFactorAuth;

        if (! $user || ! $twoFactor?->isEnabled()) {
            $this->redirect(route('login'));

            return;
        }

        $valid = false;

        if ($this->useRecoveryCode) {
            $valid = $twoFactor->verifyBackupCode($this->recovery_code);
        } else {
            $valid = $twoFactor->verify($this->code);
        }

        if (! $valid) {
            if ($this->useRecoveryCode) {
                $this->addError('recovery_code', __('The provided recovery code is invalid.'));
            } else {
                $this->addError('code', __('The provided code is invalid.'));
            }
            $this->dispatch('verify-failed');

            return;
        }

        // Clear challenge session and log in
        session()->forget('2fa_user_id');
        Auth::login($user, session()->pull('2fa_remember', false));

        $this->redirect(route('dashboard'));
    }

    public function render()
    {
        return view('livewire.auth.two-factor-challenge')
            ->layout('layouts.guest');
    }
}
