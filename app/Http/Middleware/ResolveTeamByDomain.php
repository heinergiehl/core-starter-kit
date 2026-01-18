<?php

namespace App\Http\Middleware;

use App\Domain\Tenancy\Models\Tenant;
use App\Domain\Organization\Models\Team;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTeamByDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        $tenant = tenant();

        if (!$tenant) {
            return $next($request);
        }

        $team = $tenant instanceof Tenant
            ? $tenant->team
            : Team::where('tenant_id', $tenant->getTenantKey())->first();

        if (!$team) {
            return $next($request);
        }

        $request->attributes->set('resolved_team', $team);

        $user = $request->user();

        if ($user && $this->userBelongsToTeam($user->id, $team)) {
            if ($user->current_team_id !== $team->id) {
                $user->update(['current_team_id' => $team->id]);
            }
        } elseif ($user && $this->requiresTeamAccess($request)) {
            abort(403);
        }

        return $next($request);
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
