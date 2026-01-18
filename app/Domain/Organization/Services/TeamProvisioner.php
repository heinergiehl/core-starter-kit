<?php

namespace App\Domain\Organization\Services;

use App\Domain\Organization\Enums\TeamRole;
use App\Domain\Organization\Models\Team;
use App\Domain\Organization\Services\TenantDomainValidator;
use App\Domain\Tenancy\Services\TenantProvisioner;
use App\Models\User;
use Illuminate\Support\Str;

class TeamProvisioner
{
    public function createDefaultTeam(User $user, ?string $teamName = null): Team
    {
        $name = $teamName ?: "{$user->name}'s Team";
        $slugBase = Str::slug($name) ?: Str::random(8);
        $slug = $slugBase;
        $counter = 1;

        while (Team::where('slug', $slug)->exists()) {
            $slug = "{$slugBase}-{$counter}";
            $counter++;
        }

        $domainValidator = app(TenantDomainValidator::class);
        $subdomainBase = $domainValidator->normalize($slug) ?? Str::random(8);

        if ($domainValidator->isReservedSubdomain($subdomainBase)) {
            $subdomainBase = "{$subdomainBase}-team";
        }

        $subdomain = $subdomainBase;
        $subdomainCounter = 1;

        while (Team::where('subdomain', $subdomain)->exists()) {
            $subdomain = "{$subdomainBase}-{$subdomainCounter}";
            $subdomainCounter++;
        }

        $team = Team::create([
            'name' => $name,
            'slug' => $slug,
            'subdomain' => $subdomain,
            'owner_id' => $user->id,
        ]);

        app(TenantProvisioner::class)->syncDomainsForTeam($team);

        $team->members()->attach($user->id, [
            'role' => TeamRole::Owner->value,
            'joined_at' => now(),
        ]);

        $user->update([
            'current_team_id' => $team->id,
        ]);

        return $team;
    }
}
