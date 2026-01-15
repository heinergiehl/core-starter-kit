<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return redirect()->route('login');
        }

        $team = $user->currentTeam;

        if (!$team) {
            return redirect()->route('teams.select');
        }

        if (!$team->hasActiveSubscription()) {
            return redirect()
                ->route('pricing')
                ->with('warning', __('Please complete your subscription to access the app.'));
        }

        return $next($request);
    }
}
