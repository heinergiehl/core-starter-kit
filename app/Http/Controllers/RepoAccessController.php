<?php

namespace App\Http\Controllers;

use App\Domain\RepoAccess\Services\RepoAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class RepoAccessController extends Controller
{
    public function sync(Request $request, RepoAccessService $repoAccessService): RedirectResponse|JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $isJson = $request->expectsJson();

        if (! $repoAccessService->isEnabled()) {
            $message = __('Repository access automation is not enabled.');

            if ($isJson) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        if (! $repoAccessService->hasEligiblePurchase($user)) {
            $message = __('Repository access becomes available after a successful purchase.');

            if ($isJson) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        if (! $repoAccessService->githubAccount($user)) {
            $message = __('Please connect your GitHub account first.');

            if ($isJson) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        $repoAccessService->queueGrant($user, 'manual_sync');

        if ($isJson) {
            return response()->json([
                'message' => __('Repository access sync has been queued.'),
                'status' => 'queued',
            ], 202);
        }

        return back()->with('success', __('Repository access sync has been queued.'));
    }

    public function disconnectGithub(Request $request, RepoAccessService $repoAccessService): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $repoAccessService->disconnectGithub($user);

        return back()->with('success', __('GitHub account disconnected. Connect your preferred account to continue.'));
    }

    public function status(Request $request, RepoAccessService $repoAccessService): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $enabled = $repoAccessService->isEnabled();
        $eligible = $repoAccessService->hasEligiblePurchase($user);
        $githubAccount = $repoAccessService->githubAccount($user);
        $grant = $repoAccessService->grantForUser($user);
        $grantStatus = $grant?->status?->value;
        $isGranted = in_array($grantStatus, ['invited', 'granted'], true);
        $githubUsername = trim((string) ($grant?->github_username ?? $githubAccount?->provider_name ?? ''));

        return response()->json([
            'enabled' => $enabled,
            'eligible' => $eligible,
            'github_connected' => (bool) $githubAccount,
            'github_username' => $githubUsername !== '' ? $githubUsername : null,
            'grant_status' => $grantStatus,
            'grant_label' => $grant?->status?->getLabel(),
            'grant_error' => $grant?->last_error,
            'repository' => $repoAccessService->repositoryLabel(),
            'is_granted' => $isGranted,
            'can_sync' => $enabled && $eligible && (bool) $githubAccount && ! $isGranted,
        ]);
    }
}
