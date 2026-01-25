<?php

namespace App\Livewire\Profile;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class UpdatePassword extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public function updatePassword(): void
    {
        $user = Auth::user();
        $hasExistingPassword = $user->password !== null;

        $rules = [
            'password' => ['required', Password::defaults(), 'confirmed'],
        ];

        // Only require current password if user already has one
        if ($hasExistingPassword) {
            $rules['current_password'] = ['required', 'current_password'];
        }

        $this->validate($rules);

        $user->update([
            'password' => Hash::make($this->password),
        ]);

        $this->reset(['current_password', 'password', 'password_confirmation']);

        $this->dispatch('password-updated');
        $this->dispatch('notify', type: 'success', message: __('Password updated successfully.'));
    }

    public function render()
    {
        $user = Auth::user();

        return view('livewire.profile.update-password', [
            'hasPassword' => $user->password !== null,
        ]);
    }
}
