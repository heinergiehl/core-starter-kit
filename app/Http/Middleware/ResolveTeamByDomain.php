<?php

namespace App\Http\Middleware;

use App\Domain\Organization\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTeamByDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();

        if ($host) {
            $team = $this->resolveTeam($host);

            if ($team) {
                $request->attributes->set('resolved_team', $team);

                $user = $request->user();

                if ($user && $this->userBelongsToTeam($user->id, $team)) {
                    if ($user->current_team_id !== $team->id) {
                        $user->update(['current_team_id' => $team->id]);
                    }
                } elseif ($user && $this->requiresTeamAccess($request)) {
                    abort(403);
                }
            }
        }

        return $next($request);
    }

    private function resolveTeam(string $host): ?Team
    {
        $team = Team::where('domain', $host)->first();

        if ($team) {
            return $team;
        }

        $baseDomain = config('saas.tenancy.base_domain');

        if (!$baseDomain) {
            $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
            $baseDomain = is_string($appHost) ? $appHost : null;
        }

        if (!$baseDomain || $host === $baseDomain) {
            return null;
        }

        $baseSuffix = ".{$baseDomain}";

        if (str_starts_with($host, 'www.') && substr($host, 4) === $baseDomain) {
            return null;
        }

        if (!str_ends_with($host, $baseSuffix)) {
            return null;
        }

        $subdomain = substr($host, 0, -strlen($baseSuffix));

        if ($subdomain === '' || str_contains($subdomain, '.')) {
            return null;
        }

        return Team::where(function ($query) use ($subdomain) {
            $query->where('subdomain', $subdomain)
                ->orWhere('slug', $subdomain);
        })->first();
    }

    private function userBelongsToTeam(int $userId, Team $team): bool
    {
        if ($team->owner_id === $userId) {
            return true;
        }

        return $team->members()->where('users.id', $userId)->exists();
    }

    private function requiresTeamAccess(Request $request): bool
    {
        return $request->is('dashboard')
            || $request->is('app')
            || $request->is('app/*')
            || $request->is('billing')
            || $request->is('billing/*')
            || $request->is('teams')
            || $request->is('teams/*');
    }
}
