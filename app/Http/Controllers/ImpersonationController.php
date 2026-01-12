<?php

namespace App\Http\Controllers;

use App\Domain\Identity\Services\ImpersonationService;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ImpersonationController extends Controller
{
    public function __construct(
        protected ImpersonationService $impersonationService
    ) {}

    /**
     * Start impersonating a user.
     */
    public function start(Request $request, User $user): RedirectResponse
    {
        $impersonator = $request->user();

        if (!$impersonator) {
            abort(403);
        }

        $success = $this->impersonationService->impersonate($impersonator, $user);

        if (!$success) {
            return back()->withErrors([
                'impersonation' => 'Unable to impersonate this user.',
            ]);
        }

        return redirect()->route('dashboard')
            ->with('success', "Now impersonating {$user->name}");
    }

    /**
     * Stop impersonating and return to original user.
     */
    public function stop(): RedirectResponse
    {
        $success = $this->impersonationService->stopImpersonating();

        if (!$success) {
            return redirect()->route('dashboard')
                ->withErrors(['impersonation' => 'Not currently impersonating anyone.']);
        }

        return redirect()->route('filament.admin.resources.users.index')
            ->with('success', 'Stopped impersonating. Welcome back!');
    }
}
