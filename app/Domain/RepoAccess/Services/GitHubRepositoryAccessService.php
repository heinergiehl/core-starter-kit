<?php

namespace App\Domain\RepoAccess\Services;

use App\Domain\Identity\Models\SocialAccount;
use App\Enums\OAuthProvider;
use App\Enums\RepoAccessGrantStatus;
use App\Models\User;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

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

        $username = $this->repoAccessService->selectedGitHubUsername($user);
        $token = '';
        $owner = '';
        $repository = '';
        $permission = '';

        try {
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

            if ($response->status() === 422 && $this->isAlreadyGrantedResponse($response)) {
                $message = $this->extractResponseMessage($response);
                $errors = implode(' ', $this->flattenGithubErrors($response));
                $status = str_contains(strtolower("{$message} {$errors}"), 'invited')
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

            $friendlyMessage = $this->formatGrantFailureMessage($response, $owner, $repository, $username);
            $rawMessage = $this->extractResponseMessage($response);

            if ($response->serverError() || $response->status() === 429) {
                $this->repoAccessService->upsertGrant(
                    $user,
                    RepoAccessGrantStatus::Failed,
                    [
                        'github_username' => $username,
                        'last_error' => $friendlyMessage,
                        'last_attempted_at' => now(),
                        'metadata' => [
                            'source' => $source,
                            'status' => $response->status(),
                            'github_message' => $rawMessage,
                        ],
                    ]
                );

                throw new RuntimeException($friendlyMessage);
            }

            $this->repoAccessService->upsertGrant(
                $user,
                RepoAccessGrantStatus::Failed,
                [
                    'github_username' => $username,
                    'last_error' => $friendlyMessage,
                    'last_attempted_at' => now(),
                    'metadata' => [
                        'source' => $source,
                        'status' => $response->status(),
                        'github_message' => $rawMessage,
                    ],
                ]
            );

            Log::warning('GitHub repository access request was rejected.', [
                'user_id' => $user->id,
                'github_username' => $username,
                'repository' => "{$owner}/{$repository}",
                'status' => $response->status(),
                'message' => $friendlyMessage,
                'raw_message' => $rawMessage,
                'source' => $source,
            ]);
        } catch (Throwable $exception) {
            $safeMessage = $this->formatThrowableMessage($exception, $owner, $repository, $username);
            $rawExceptionMessage = trim($exception->getMessage());

            $this->repoAccessService->upsertGrant(
                $user,
                RepoAccessGrantStatus::Failed,
                [
                    'github_username' => $username,
                    'last_error' => $safeMessage,
                    'last_attempted_at' => now(),
                    'metadata' => [
                        'source' => $source,
                        'exception' => $exception::class,
                        'exception_message' => substr($rawExceptionMessage, 0, 512),
                    ],
                ]
            );

            Log::error('GitHub repository access grant failed unexpectedly.', [
                'user_id' => $user->id,
                'github_username' => $username,
                'source' => $source,
                'exception' => $exception::class,
                'message' => $safeMessage,
                'raw_message' => $rawExceptionMessage,
            ]);

            throw $exception;
        }
    }

    private function extractResponseMessage(Response $response): string
    {
        $message = trim((string) $response->json('message', ''));
        if ($message !== '') {
            return $message;
        }

        return trim((string) $response->body());
    }

    /**
     * @return array<int, string>
     */
    private function flattenGithubErrors(Response $response): array
    {
        $errors = $response->json('errors');
        if (! is_array($errors)) {
            return [];
        }

        $messages = [];

        foreach ($errors as $error) {
            if (is_string($error)) {
                $normalized = trim($error);
                if ($normalized !== '') {
                    $messages[] = $normalized;
                }

                continue;
            }

            if (! is_array($error)) {
                continue;
            }

            foreach (['message', 'code', 'field', 'resource'] as $key) {
                $value = trim((string) ($error[$key] ?? ''));
                if ($value !== '') {
                    $messages[] = $value;
                }
            }
        }

        return array_values(array_unique($messages));
    }

    private function formatGrantFailureMessage(
        Response $response,
        string $owner,
        string $repository,
        ?string $username,
    ): string {
        $status = $response->status();
        $repositoryLabel = $owner !== '' && $repository !== ''
            ? "{$owner}/{$repository}"
            : $this->repoAccessService->repositoryLabel();
        $message = strtolower($this->extractResponseMessage($response));
        $errors = strtolower(implode(' ', $this->flattenGithubErrors($response)));
        $combined = trim("{$message} {$errors}");

        if ($status === 401) {
            return __('GitHub token is invalid. Please check GH_REPO_ACCESS_TOKEN.');
        }

        if ($status === 403) {
            if (str_contains($combined, 'rate limit')) {
                return __('GitHub rate limit reached. Please try again in about a minute.');
            }

            return __('GitHub token is missing collaborator permissions for :repo.', ['repo' => $repositoryLabel]);
        }

        if ($status === 404) {
            return __('Repository :repo was not found or token has no access.', ['repo' => $repositoryLabel]);
        }

        if ($status === 422) {
            if (str_contains($combined, 'repository owner cannot be a collaborator')) {
                return __('The selected username owns :repo and already has access. Choose the customer GitHub account instead.', ['repo' => $repositoryLabel]);
            }

            if (str_contains($combined, 'already') && (str_contains($combined, 'collaborator') || str_contains($combined, 'invited'))) {
                return __('This GitHub account already has access or has a pending invitation.');
            }

            if (str_contains($combined, 'could not resolve to a user') || str_contains($combined, 'not found')) {
                if (is_string($username) && trim($username) !== '') {
                    return __('GitHub could not validate @:username. Choose an account from search results and try again.', ['username' => trim($username)]);
                }

                return __('GitHub could not validate the selected username. Choose an account from search results and try again.');
            }

            return __('GitHub rejected this collaborator request. Verify your selected account and repository settings.');
        }

        if ($status === 429) {
            return __('GitHub rate limit reached. Please try again in about a minute.');
        }

        if ($response->serverError()) {
            return __('GitHub API is temporarily unavailable. Please retry shortly.');
        }

        return __('Could not grant repository access right now. Please try again.');
    }

    private function formatThrowableMessage(
        Throwable $exception,
        string $owner,
        string $repository,
        ?string $username,
    ): string {
        if ($exception instanceof RequestException && $exception->response !== null) {
            return $this->formatGrantFailureMessage($exception->response, $owner, $repository, $username);
        }

        $message = trim($exception->getMessage());
        if ($message === '') {
            return __('Unexpected error while granting GitHub repository access.');
        }

        $normalized = strtolower($message);
        if (
            str_contains($normalized, 'connection refused')
            || str_contains($normalized, 'curl error')
            || str_contains($normalized, 'operation timed out')
            || str_contains($normalized, 'timeout')
        ) {
            return __('GitHub API could not be reached. Please try again in a moment.');
        }

        if (str_starts_with($message, 'HTTP request returned status code')) {
            return __('GitHub rejected this collaborator request. Verify your selected account and repository settings.');
        }

        if (str_starts_with($message, 'GitHub repository access request failed:')) {
            $trimmed = trim(substr($message, strlen('GitHub repository access request failed:')));
            if ($trimmed !== '') {
                return $trimmed;
            }
        }

        return $message;
    }

    private function resolveGitHubUsername(User $user, string $token): ?string
    {
        $selected = trim((string) ($this->repoAccessService->selectedGitHubUsername($user) ?? ''));
        if ($this->looksLikeGitHubUsername($selected)) {
            return $selected;
        }

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
                null,
                false,
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

    private function isAlreadyGrantedResponse(Response $response): bool
    {
        $combined = strtolower(trim($this->extractResponseMessage($response).' '.implode(' ', $this->flattenGithubErrors($response))));

        if ($combined === '') {
            return false;
        }

        return str_contains($combined, 'already')
            && (str_contains($combined, 'collaborator') || str_contains($combined, 'invited'));
    }
}
