<?php

namespace App\Livewire\Auth;

use App\Domain\Organization\Services\TeamProvisioner;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Livewire\Component;

class Register extends Component
{
    public string $name = '';
    public string $team_name = '';
    public string $email = '';
    public string $password = '';
    public string $password_confirmation = '';

    public function register(TeamProvisioner $teams): void
    {
        $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'team_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $this->name,
            'email' => strtolower($this->email),
            'password' => Hash::make($this->password),
        ]);

        $teams->createDefaultTeam($user, $this->team_name ?: null);

        event(new Registered($user));

        Auth::login($user);

        $this->redirect(route('dashboard'));
    }

    public function render()
    {
        return view('livewire.auth.register')
            ->layout('layouts.guest');
    }
}
