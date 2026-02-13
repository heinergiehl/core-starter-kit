<?php

namespace App\Http\Controllers;

use App\Domain\RepoAccess\Services\RepoAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RepoAccessController extends Controller
{
    public function sync(Request $request, RepoAccessService $repoAccessService): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        if (! $repoAccessService->isEnabled()) {
            return back()->with('error', __('Repository access automation is not enabled.'));
        }

        if (! $repoAccessService->hasEligiblePurchase($user)) {
            return back()->with('error', __('Repository access becomes available after a successful purchase.'));
        }

        $repoAccessService->queueGrant($user, 'manual_sync');

        return back()->with('success', __('Repository access sync has been queued.'));
    }

    public function disconnectGithub(Request $request, RepoAccessService $repoAccessService): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $repoAccessService->disconnectGithub($user);

        return back()->with('success', __('GitHub account disconnected. Connect your preferred account to continue.'));
    }
}
