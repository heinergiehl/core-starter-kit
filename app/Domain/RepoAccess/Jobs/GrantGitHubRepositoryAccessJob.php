<?php

namespace App\Domain\RepoAccess\Jobs;

use App\Domain\RepoAccess\Services\GitHubRepositoryAccessService;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class GrantGitHubRepositoryAccessJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 30;

    public array $backoff = [10, 30, 120];

    public function __construct(
        public readonly int $userId,
        public readonly string $source,
    ) {
        $queue = trim((string) config('repo_access.queue'));

        if ($queue !== '') {
            $this->onQueue($queue);
        }
    }

    public function handle(GitHubRepositoryAccessService $service): void
    {
        $user = User::query()->find($this->userId);

        if (! $user) {
            return;
        }

        $service->grantReadAccess($user, $this->source);
    }
}
