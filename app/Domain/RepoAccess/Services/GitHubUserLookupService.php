<?php

namespace App\Domain\RepoAccess\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

class GitHubUserLookupService
{
    /**
     * @return array<int, array{login:string,id:int,avatar_url:?string,html_url:?string}>
     */
    public function searchUsers(string $query, int $limit = 8): array
    {
        $term = trim($query);
        if ($term === '') {
            return [];
        }

        $perPage = max(1, min($limit, 20));
        $response = $this->githubClient()->get('/search/users', [
            'q' => "{$term} in:login type:user",
            'per_page' => $perPage,
            'page' => 1,
        ]);

        if (! $response->ok()) {
            return [];
        }

        $items = $response->json('items');
        if (! is_array($items)) {
            return [];
        }

        return collect($items)
            ->map(function ($item): ?array {
                if (! is_array($item)) {
                    return null;
                }

                $login = trim((string) ($item['login'] ?? ''));
                $id = (int) ($item['id'] ?? 0);
                if ($login === '' || $id <= 0) {
                    return null;
                }

                return [
                    'login' => $login,
                    'id' => $id,
                    'avatar_url' => $item['avatar_url'] ?? null,
                    'html_url' => $item['html_url'] ?? null,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{login:string,id:int,avatar_url:?string,html_url:?string}|null
     */
    public function findUserByLogin(string $login): ?array
    {
        $normalized = trim($login);
        if ($normalized === '') {
            return null;
        }

        $response = $this->githubClient()->get('/users/'.rawurlencode($normalized));
        if (! $response->ok()) {
            return null;
        }

        $resolvedLogin = trim((string) $response->json('login', ''));
        $id = (int) $response->json('id', 0);
        if ($resolvedLogin === '' || $id <= 0) {
            return null;
        }

        return [
            'login' => $resolvedLogin,
            'id' => $id,
            'avatar_url' => $response->json('avatar_url'),
            'html_url' => $response->json('html_url'),
        ];
    }

    /**
     * @return array{login:string,id:int,avatar_url:?string,html_url:?string}|null
     */
    public function findUserById(int $id): ?array
    {
        if ($id <= 0) {
            return null;
        }

        $response = $this->githubClient()->get('/user/'.rawurlencode((string) $id));
        if (! $response->ok()) {
            return null;
        }

        $resolvedLogin = trim((string) $response->json('login', ''));
        $resolvedId = (int) $response->json('id', 0);
        if ($resolvedLogin === '' || $resolvedId <= 0) {
            return null;
        }

        return [
            'login' => $resolvedLogin,
            'id' => $resolvedId,
            'avatar_url' => $response->json('avatar_url'),
            'html_url' => $response->json('html_url'),
        ];
    }

    private function githubClient(): PendingRequest
    {
        $token = trim((string) config('repo_access.github.token', ''));

        $request = Http::asJson()
            ->acceptJson()
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

        if ($token !== '') {
            $request = $request->withToken($token);
        }

        return $request;
    }
}
