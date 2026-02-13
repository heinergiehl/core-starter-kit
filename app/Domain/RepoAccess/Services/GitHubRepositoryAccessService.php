<?php

namespace App\Domain\RepoAccess\Services;

use App\Domain\Identity\Models\SocialAccount;
use App\Enums\RepoAccessGrantStatus;
use App\Enums\OAuthProvider;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class GitHubRepositoryAccessService
{
    public function __construct(
        private readonly RepoAccessService $repoAccessService,
    ) {}

    public function grantReadAccess(User $user, string $source): void
    {
        if (! $this->repoAccessService->isEnabled()) {
            return;
        }

        if (strtolower((string) config('repo_access.provider', 'github')) !== 'github') {
            return;
        }

        $this->repoAccessService->upsertGrant(
            $user,
            RepoAccessGrantStatus::Processing,
            [
                'last_error' => null,
                'last_attempted_at' => now(),
                'metadata' => [
                    'source' => $source,
                ],
            ]
        );

        $token = trim((string) config('repo_access.github.token'));
        $owner = trim((string) config('repo_access.github.owner'));
        $repository = trim((string) config('repo_access.github.repository'));
        $permission = trim((string) config('repo_access.github.permission', 'pull'));

        if ($token === '' || $owner === '' || $repository === '') {
            $this->repoAccessService->upsertGrant(
                $user,
                RepoAccessGrantStatus::Failed,
                [
                    'last_error' => 'GitHub repository access configuration is incomplete.',
                    'last_attempted_at' => now(),
                    'metadata' => [
                        'source' => $source,
                    ],
                ]
            );

            Log::warning('Repo access grant skipped: GitHub configuration is incomplete.', [
                'user_id' => $user->id,
                'source' => $source,
            ]);

            return;
        }

        $username = $this->resolveGitHubUsername($user, $token);

        if (! $username) {
            $this->repoAccessService->upsertGrant(
                $user,
                RepoAccessGrantStatus::AwaitingGitHubLink,
                [
                    'last_error' => null,
                    'github_username' => null,
                    'last_attempted_at' => now(),
                    'metadata' => [
                        'source' => $source,
                    ],
                ]
            );

            Log::warning('Repo access grant skipped: user has no linked GitHub account.', [
                'user_id' => $user->id,
                'source' => $source,
            ]);

            return;
        }

        $response = $this->githubClient($token)->put(
            sprintf(
                '/repos/%s/%s/collaborators/%s',
                rawurlencode($owner),
                rawurlencode($repository),
                rawurlencode($username),
            ),
            [
                'permission' => $permission === '' ? 'pull' : $permission,
            ]
        );

        if (in_array($response->status(), [201, 204], true)) {
            $status = $response->status() === 201 ? RepoAccessGrantStatus::Invited : RepoAccessGrantStatus::Granted;
            $this->repoAccessService->upsertGrant(
                $user,
                $status,
                [
                    'github_username' => $username,
                    'last_error' => null,
                    'last_attempted_at' => now(),
                    'invited_at' => $response->status() === 201 ? now() : null,
                    'granted_at' => $response->status() === 204 ? now() : null,
                    'metadata' => [
                        'source' => $source,
                    ],
                ]
            );

            Log::info('GitHub repository access granted.', [
                'user_id' => $user->id,
                'github_username' => $username,
                'repository' => "{$owner}/{$repository}",
                'source' => $source,
            ]);

            return;
        }

        if ($response->status() === 422 && $this->isAlreadyGrantedMessage((string) $response->json('message'))) {
            $message = (string) $response->json('message', '');
            $status = str_contains(strtolower($message), 'invited')
                ? RepoAccessGrantStatus::Invited
                : RepoAccessGrantStatus::Granted;

            $this->repoAccessService->upsertGrant(
                $user,
                $status,
                [
                    'github_username' => $username,
                    'last_error' => null,
                    'last_attempted_at' => now(),
                    'invited_at' => $status === RepoAccessGrantStatus::Invited ? now() : null,
                    'granted_at' => $status === RepoAccessGrantStatus::Granted ? now() : null,
                    'metadata' => [
                        'source' => $source,
                        'message' => $message,
                    ],
                ]
            );

            Log::info('GitHub repository access already granted or invitation pending.', [
                'user_id' => $user->id,
                'github_username' => $username,
                'repository' => "{$owner}/{$repository}",
                'source' => $source,
            ]);

            return;
        }

        $message = (string) ($response->json('message') ?: $response->body());

        if ($response->serverError() || $response->status() === 429) {
            $this->repoAccessService->upsertGrant(
                $user,
                RepoAccessGrantStatus::Failed,
                [
                    'github_username' => $username,
                    'last_error' => $message,
                    'last_attempted_at' => now(),
                    'metadata' => [
                        'source' => $source,
                        'status' => $response->status(),
                    ],
                ]
            );

            throw new RuntimeException('GitHub repository access request failed: '.$message);
        }

        $this->repoAccessService->upsertGrant(
            $user,
            RepoAccessGrantStatus::Failed,
            [
                'github_username' => $username,
                'last_error' => $message,
                'last_attempted_at' => now(),
                'metadata' => [
                    'source' => $source,
                    'status' => $response->status(),
                ],
            ]
        );

        Log::warning('GitHub repository access request was rejected.', [
            'user_id' => $user->id,
            'github_username' => $username,
            'repository' => "{$owner}/{$repository}",
            'status' => $response->status(),
            'message' => $message,
            'source' => $source,
        ]);
    }

    private function resolveGitHubUsername(User $user, string $token): ?string
    {
        $account = SocialAccount::query()
            ->where('user_id', $user->id)
            ->where('provider', OAuthProvider::GitHub)
            ->latest('id')
            ->first();

        if (! $account) {
            return null;
        }

        $providerId = trim((string) $account->provider_id);

        if ($providerId !== '') {
            $response = $this->githubClient($token)->get('/user/'.rawurlencode($providerId));

            if ($response->ok()) {
                $login = trim((string) $response->json('login'));

                if ($login !== '') {
                    if ($account->provider_name !== $login) {
                        $account->forceFill(['provider_name' => $login])->saveQuietly();
                    }

                    return $login;
                }
            }

            if ($response->serverError() || $response->status() === 429) {
                throw new RuntimeException('GitHub username lookup failed for provider account id: '.$providerId);
            }
        }

        $fallback = trim((string) $account->provider_name);

        if ($this->looksLikeGitHubUsername($fallback)) {
            return $fallback;
        }

        return null;
    }

    private function githubClient(string $token): PendingRequest
    {
        return Http::asJson()
            ->acceptJson()
            ->withToken($token)
            ->withHeaders([
                'User-Agent' => config('app.name', 'Laravel').' RepoAccess',
                'X-GitHub-Api-Version' => '2022-11-28',
            ])
            ->baseUrl(rtrim((string) config('repo_access.github.api_url', 'https://api.github.com'), '/'))
            ->timeout((int) config('repo_access.github.timeout', 15))
            ->retry(
                (int) config('repo_access.github.retries', 2),
                (int) config('repo_access.github.retry_delay_ms', 400),
            );
    }

    private function looksLikeGitHubUsername(string $username): bool
    {
        if ($username === '') {
            return false;
        }

        if (str_starts_with($username, '-') || str_ends_with($username, '-')) {
            return false;
        }

        return preg_match('/^[A-Za-z0-9-]{1,39}$/', $username) === 1;
    }

    private function isAlreadyGrantedMessage(string $message): bool
    {
        $normalized = strtolower(trim($message));

        if ($normalized === '') {
            return false;
        }

        return str_contains($normalized, 'already')
            && (str_contains($normalized, 'collaborator') || str_contains($normalized, 'invited'));
    }
}
