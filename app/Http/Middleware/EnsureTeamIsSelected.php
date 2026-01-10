<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTeamIsSelected
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && !$user->current_team_id) {
            $teamIds = $user->teams()->pluck('teams.id');

            if ($teamIds->count() === 1) {
                $user->update(['current_team_id' => $teamIds->first()]);
            } else {
                return redirect()->route('teams.select');
            }
        }

        return $next($request);
    }
}
