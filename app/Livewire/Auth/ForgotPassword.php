<?php

namespace App\Livewire\Auth;

use Illuminate\Support\Facades\Password;
use Livewire\Component;

class ForgotPassword extends Component
{
    public string $email = '';

    public function sendResetLink(): void
    {
        $this->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink(['email' => $this->email]);

        if ($status === Password::RESET_LINK_SENT) {
            $this->dispatch('notify', type: 'success', message: __($status));
            $this->dispatch('password-reset-sent');
            $this->email = '';
        } else {
            $this->addError('email', __($status));
            $this->dispatch('password-reset-failed');
        }
    }

    public function render()
    {
        return view('livewire.auth.forgot-password')
            ->layout('layouts.guest');
    }
}
