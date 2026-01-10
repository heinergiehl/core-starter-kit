<?php

namespace App\Http\Controllers\Organization;

use App\Domain\Organization\Models\Team;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TeamController
{
    public function select(Request $request): View
    {
        $teams = $request->user()?->teams()->orderBy('name')->get() ?? collect();

        return view('teams.select', [
            'teams' => $teams,
        ]);
    }

    public function switch(Request $request, Team $team): RedirectResponse
    {
        $user = $request->user();

        if (!$user || !$user->teams()->whereKey($team->id)->exists()) {
            abort(403);
        }

        $user->update(['current_team_id' => $team->id]);

        return redirect()->intended(route('dashboard'));
    }
}
