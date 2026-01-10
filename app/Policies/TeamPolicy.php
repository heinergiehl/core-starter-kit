<?php

namespace App\Policies;

use App\Domain\Organization\Enums\TeamRole;
use App\Domain\Organization\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function view(User $user, Team $team): bool
    {
        return $team->members()->where('users.id', $user->id)->exists() || $team->isOwner($user);
    }

    public function update(User $user, Team $team): bool
    {
        return $team->isOwner($user) || $this->hasRole($user, $team, [TeamRole::Admin]);
    }

    public function invite(User $user, Team $team): bool
    {
        return $team->isOwner($user) || $this->hasRole($user, $team, [TeamRole::Admin]);
    }

    public function billing(User $user, Team $team): bool
    {
        return $team->isOwner($user) || $this->hasRole($user, $team, [TeamRole::Billing, TeamRole::Admin]);
    }

    private function hasRole(User $user, Team $team, array $roles): bool
    {
        $role = $team->members()
            ->where('users.id', $user->id)
            ->value('team_user.role');

        if (!$role) {
            return false;
        }

        return in_array($role, array_map(fn (TeamRole $roleEnum) => $roleEnum->value, $roles), true);
    }
}
