<?php

namespace App\Domain\RepoAccess\Services;

use App\Domain\Billing\Models\Order;
use App\Domain\Identity\Models\SocialAccount;
use App\Domain\RepoAccess\Jobs\GrantGitHubRepositoryAccessJob;
use App\Domain\RepoAccess\Models\RepoAccessGrant;
use App\Enums\OAuthProvider;
use App\Enums\OrderStatus;
use App\Enums\RepoAccessGrantStatus;
use App\Models\User;

class RepoAccessService
{
    public function isEnabled(): bool
    {
        return (bool) config('repo_access.enabled', false);
    }

    public function repositoryOwner(): string
    {
        return trim((string) config('repo_access.github.owner'));
    }

    public function repositoryName(): string
    {
        return trim((string) config('repo_access.github.repository'));
    }

    public function repositoryLabel(): string
    {
        $owner = $this->repositoryOwner();
        $name = $this->repositoryName();

        if ($owner === '' || $name === '') {
            return __('Not configured');
        }

        return "{$owner}/{$name}";
    }

    public function hasEligiblePurchase(User $user): bool
    {
        if ($user->hasActiveSubscription()) {
            return true;
        }

        return Order::query()
            ->where('user_id', $user->id)
            ->whereIn('status', [OrderStatus::Paid->value, OrderStatus::Completed->value])
            ->exists();
    }

    public function githubAccount(User $user): ?SocialAccount
    {
        return SocialAccount::query()
            ->where('user_id', $user->id)
            ->where('provider', OAuthProvider::GitHub)
            ->latest('id')
            ->first();
    }

    public function grantForUser(User $user): ?RepoAccessGrant
    {
        return RepoAccessGrant::query()
            ->where('user_id', $user->id)
            ->where('provider', 'github')
            ->where('repository_owner', $this->repositoryOwner())
            ->where('repository_name', $this->repositoryName())
            ->first();
    }

    public function selectedGitHubUsername(User $user): ?string
    {
        $grant = $this->grantForUser($user);
        $username = trim((string) ($grant?->github_username ?? ''));

        return $username !== '' ? $username : null;
    }

    public function effectiveGitHubUsername(User $user): ?string
    {
        $selected = $this->selectedGitHubUsername($user);
        if ($selected !== null) {
            return $selected;
        }

        $account = $this->githubAccount($user);
        $fallback = trim((string) ($account?->provider_name ?? ''));

        return $fallback !== '' ? $fallback : null;
    }

    public function queueGrant(User $user, string $source): void
    {
        if (! $this->isEnabled()) {
            return;
        }

        $this->upsertGrant(
            $user,
            RepoAccessGrantStatus::Queued,
            [
                'last_error' => null,
                'metadata' => [
                    'source' => $source,
                ],
            ]
        );

        GrantGitHubRepositoryAccessJob::dispatch($user->id, $source);
    }

    public function disconnectGithub(User $user): void
    {
        SocialAccount::query()
            ->where('user_id', $user->id)
            ->where('provider', OAuthProvider::GitHub)
            ->delete();

        $this->upsertGrant(
            $user,
            RepoAccessGrantStatus::AwaitingGitHubLink,
            [
                'github_username' => null,
                'last_error' => null,
                'metadata' => [
                    'source' => 'disconnect',
                ],
            ]
        );
    }

    public function setGitHubUsername(User $user, string $username, ?int $githubId = null, string $source = 'username_selected'): RepoAccessGrant
    {
        $normalized = trim($username);
        $metadata = [
            'source' => $source,
        ];

        if ($githubId !== null && $githubId > 0) {
            $metadata['github_user_id'] = $githubId;
        }

        return $this->upsertGrant(
            $user,
            RepoAccessGrantStatus::AwaitingGitHubLink,
            [
                'github_username' => $normalized,
                'last_error' => null,
                'invited_at' => null,
                'granted_at' => null,
                'metadata' => $metadata,
            ]
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function upsertGrant(User $user, RepoAccessGrantStatus $status, array $attributes = []): RepoAccessGrant
    {
        $owner = $this->repositoryOwner();
        $repository = $this->repositoryName();
        $existing = $this->grantForUser($user);
        $existingMetadata = is_array($existing?->metadata) ? $existing->metadata : [];
        $newMetadata = is_array($attributes['metadata'] ?? null) ? $attributes['metadata'] : [];
        $attributes['metadata'] = array_merge($existingMetadata, $newMetadata);

        return RepoAccessGrant::query()->updateOrCreate(
            [
                'user_id' => $user->id,
                'provider' => 'github',
                'repository_owner' => $owner,
                'repository_name' => $repository,
            ],
            array_merge(
                [
                    'status' => $status,
                ],
                $attributes,
            ),
        );
    }
}
