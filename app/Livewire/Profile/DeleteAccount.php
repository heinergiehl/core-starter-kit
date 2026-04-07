<?php

namespace App\Livewire\Profile;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Livewire\Component;

class DeleteAccount extends Component
{
    public string $password = '';

    public bool $showModal = false;

    public function openModal(): void
    {
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->password = '';
        $this->resetErrorBag();
    }

    public function deleteAccount(): void
    {
        $this->validate([
            'password' => ['required'],
        ]);

        $user = Auth::user();

        if (! Hash::check($this->password, $user->password)) {
            $this->addError('password', __('The password is incorrect.'));

            return;
        }

        Auth::logout();

        if (request()->hasSession()) {
            request()->session()->invalidate();
            request()->session()->regenerateToken();
        }

        $user->delete();

        $this->redirect('/', navigate: true);
    }

    public function render()
    {
        return view('livewire.profile.delete-account');
    }
}
