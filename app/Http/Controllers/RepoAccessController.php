<?php

namespace App\Http\Controllers;

use App\Domain\RepoAccess\Services\GitHubUserLookupService;
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

        if ($repoAccessService->effectiveGitHubUsername($user) === null) {
            $message = __('Please select your GitHub username first.');

            if ($isJson) {
                return response()->json(['message' => $message], 422);
            }

            return back()->with('error', $message);
        }

        $existingGrant = $repoAccessService->grantForUser($user);
        $existingStatus = $existingGrant?->status?->value;
        if (in_array($existingStatus, ['queued', 'processing'], true)) {
            $message = __('Repository access grant is already in progress. Please wait a moment.');

            if ($isJson) {
                return response()->json([
                    'message' => $message,
                    'status' => $existingStatus,
                ], 202);
            }

            return back()->with('info', $message);
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

    public function searchGithubUsers(
        Request $request,
        RepoAccessService $repoAccessService,
        GitHubUserLookupService $lookupService
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user, 403);

        if (! $repoAccessService->isEnabled()) {
            return response()->json([
                'message' => __('Repository access automation is not enabled.'),
                'items' => [],
            ], 422);
        }

        if (! $repoAccessService->hasEligiblePurchase($user)) {
            return response()->json([
                'message' => __('Repository access becomes available after a successful purchase.'),
                'items' => [],
            ], 422);
        }

        $validated = $request->validate([
            'q' => ['required', 'string', 'min:2', 'max:39'],
        ]);

        return response()->json([
            'items' => $lookupService->searchUsers($validated['q']),
        ]);
    }

    public function selectGithubUsername(
        Request $request,
        RepoAccessService $repoAccessService,
        GitHubUserLookupService $lookupService
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user, 403);

        if (! $repoAccessService->isEnabled()) {
            return response()->json([
                'message' => __('Repository access automation is not enabled.'),
            ], 422);
        }

        if (! $repoAccessService->hasEligiblePurchase($user)) {
            return response()->json([
                'message' => __('Repository access becomes available after a successful purchase.'),
            ], 422);
        }

        $validated = $request->validate([
            'login' => ['required', 'string', 'max:39', 'regex:/^[A-Za-z0-9](?:[A-Za-z0-9-]{0,37}[A-Za-z0-9])?$/'],
            'id' => ['nullable', 'integer', 'min:1'],
        ]);

        $resolved = null;
        if (! empty($validated['id'])) {
            $resolved = $lookupService->findUserById((int) $validated['id']);
        }

        if ($resolved === null) {
            $resolved = $lookupService->findUserByLogin($validated['login']);
        }

        if ($resolved === null) {
            return response()->json([
                'message' => __('GitHub user could not be validated. Please choose a valid username.'),
            ], 422);
        }

        $repoAccessService->setGitHubUsername(
            $user,
            $resolved['login'],
            $resolved['id'],
            'username_selected'
        );

        return response()->json([
            'message' => __('GitHub username selected. Confirm to grant repository access.'),
            'user' => $resolved,
        ]);
    }

    public function status(Request $request, RepoAccessService $repoAccessService): JsonResponse
    {
        $user = $request->user();
        abort_unless($user, 403);

        $enabled = $repoAccessService->isEnabled();
        $eligible = $repoAccessService->hasEligiblePurchase($user);
        $githubAccount = $repoAccessService->githubAccount($user);
        $grant = $repoAccessService->grantForUser($user);
        $selectedUsername = trim((string) ($grant?->github_username ?? ''));
        $accountUsername = trim((string) ($githubAccount?->provider_name ?? ''));
        $effectiveUsername = $selectedUsername !== '' ? $selectedUsername : $accountUsername;
        $grantStatus = $grant?->status?->value;
        $isGranted = in_array($grantStatus, ['invited', 'granted'], true);
        $isInProgress = in_array($grantStatus, ['queued', 'processing'], true);
        $isStale = $isInProgress
            && $grant?->updated_at !== null
            && $grant->updated_at->lt(now()->subMinutes(2));

        return response()->json([
            'enabled' => $enabled,
            'eligible' => $eligible,
            'github_connected' => (bool) $githubAccount,
            'github_username' => $effectiveUsername !== '' ? $effectiveUsername : null,
            'github_username_selected' => $selectedUsername !== '',
            'grant_status' => $grantStatus,
            'grant_label' => $grant?->status?->getLabel(),
            'grant_error' => $grant?->last_error,
            'last_attempted_at' => $grant?->last_attempted_at?->toIso8601String(),
            'repository' => $repoAccessService->repositoryLabel(),
            'is_granted' => $isGranted,
            'is_in_progress' => $isInProgress,
            'is_stale' => $isStale,
            'can_sync' => $enabled && $eligible && $effectiveUsername !== '' && ! $isGranted && ! $isInProgress,
        ]);
    }
}
