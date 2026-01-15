<?php

namespace App\Http\Controllers\Organization;

use App\Domain\Organization\Services\TeamInvitationService;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class TeamInvitationController
{
    public function __construct(private readonly TeamInvitationService $invitationService)
    {
    }

    public function show(Request $request, string $token): View
    {
        $invitation = $this->invitationService->findValidInvitation($token);

        if (!$invitation) {
            abort(404);
        }

        $user = $request->user();
        $canAccept = $user && strcasecmp($user->email, $invitation->email) === 0;
        $userExists = User::query()->where('email', $invitation->email)->exists();

        return view('invitations.accept', [
            'invitation' => $invitation,
            'canAccept' => $canAccept,
            'userExists' => $userExists,
        ]);
    }

    public function store(Request $request, string $token): RedirectResponse
    {
        if (!$request->user()) {
            return redirect()->guest(route('login'));
        }

        $invitation = $this->invitationService->findValidInvitation($token);

        if (!$invitation) {
            return redirect()->route('home')->withErrors([
                'invitation' => 'This invitation is no longer valid.',
            ]);
        }

        try {
            $this->invitationService->acceptInvitation($invitation, $request->user());
        } catch (ValidationException $exception) {
            return redirect()
                ->route('invitations.accept', ['token' => $token])
                ->withErrors($exception->errors());
        }

        return redirect()->route('dashboard')->with('status', 'Invitation accepted.');
    }

    public function register(Request $request, string $token): RedirectResponse
    {
        $invitation = $this->invitationService->findValidInvitation($token);

        if (!$invitation) {
            return redirect()->route('home')->withErrors([
                'invitation' => 'This invitation is no longer valid.',
            ]);
        }

        $existingUser = User::query()->where('email', $invitation->email)->first();

        if ($existingUser) {
            return redirect()->route('login')->withErrors([
                'email' => 'This email already has an account. Please sign in to accept the invite.',
            ]);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($invitation->email),
            'password' => Hash::make($data['password']),
        ]);

        $user->forceFill([
            'email_verified_at' => now(),
        ])->save();

        Auth::login($user);

        try {
            $this->invitationService->acceptInvitation($invitation, $user);
        } catch (ValidationException $exception) {
            return redirect()
                ->route('invitations.accept', ['token' => $token])
                ->withErrors($exception->errors());
        }

        return redirect()->route('dashboard')->with('status', 'Invitation accepted.');
    }
}
