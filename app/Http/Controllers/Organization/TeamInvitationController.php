<?php

namespace App\Http\Controllers\Organization;

use App\Domain\Organization\Services\TeamInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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

        return view('invitations.accept', [
            'invitation' => $invitation,
            'canAccept' => $canAccept,
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
}
