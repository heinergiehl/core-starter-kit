<?php

namespace App\Domain\Organization\Services;

use App\Domain\Billing\Services\EntitlementService;
use App\Domain\Organization\Enums\TeamRole;
use App\Domain\Organization\Models\Team;
use App\Domain\Organization\Models\TeamInvitation;
use App\Jobs\SyncSeatQuantityJob;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TeamInvitationService
{
    public function __construct(private readonly EntitlementService $entitlementService)
    {
    }

    public function createInvitation(Team $team, User $inviter, string $email, TeamRole $role): TeamInvitation
    {
        $this->assertInvitesAllowed($team);
        $this->assertNotMember($team, $email);

        $expiresAt = now()->addDays((int) config('saas.invites.expires_days', 7));
        $existing = TeamInvitation::query()
            ->where('team_id', $team->id)
            ->where('email', $email)
            ->whereNull('accepted_at')
            ->first();

        if ($existing) {
            $existing->update([
                'role' => $role->value,
                'invited_by_user_id' => $inviter->id,
                'token' => Str::random(64),
                'expires_at' => $expiresAt,
            ]);

            $this->notifyInvite($existing);

            return $existing;
        }

        $invitation = TeamInvitation::create([
            'team_id' => $team->id,
            'invited_by_user_id' => $inviter->id,
            'email' => $email,
            'role' => $role->value,
            'token' => Str::random(64),
            'expires_at' => $expiresAt,
        ]);

        $this->notifyInvite($invitation);

        return $invitation;
    }

    public function acceptInvitation(TeamInvitation $invitation, User $user): void
    {
        if ($invitation->accepted_at || ($invitation->expires_at && $invitation->expires_at->isPast())) {
            throw ValidationException::withMessages([
                'invitation' => 'This invitation is no longer valid.',
            ]);
        }

        if (strcasecmp($invitation->email, $user->email) !== 0) {
            throw ValidationException::withMessages([
                'invitation' => 'Please sign in with the email address that received the invitation.',
            ]);
        }

        $entitlements = $this->entitlementService->forTeam($invitation->team);

        if (!$entitlements->get('has_available_seats', false)) {
            throw ValidationException::withMessages([
                'invitation' => 'This workspace has reached its seat limit.',
            ]);
        }

        $invitation->team->members()->syncWithoutDetaching([
            $user->id => [
                'role' => $invitation->role,
                'joined_at' => now(),
            ],
        ]);

        $invitation->update([
            'accepted_at' => now(),
        ]);

        if (!$user->current_team_id) {
            $user->update([
                'current_team_id' => $invitation->team_id,
            ]);
        }

        SyncSeatQuantityJob::dispatch($invitation->team_id);
    }

    public function findValidInvitation(string $token): ?TeamInvitation
    {
        return TeamInvitation::query()
            ->where('token', $token)
            ->whereNull('accepted_at')
            ->where(function ($query): void {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();
    }

    private function assertInvitesAllowed(Team $team): void
    {
        $entitlements = $this->entitlementService->forTeam($team);

        if (!$entitlements->get('can_invite_members', false)) {
            throw ValidationException::withMessages([
                'email' => 'Seat limit reached. Upgrade your plan to invite more members.',
            ]);
        }
    }

    private function assertNotMember(Team $team, string $email): void
    {
        $isMember = $team->members()
            ->where('users.email', $email)
            ->exists();

        if ($isMember) {
            throw ValidationException::withMessages([
                'email' => 'This user is already a member of the workspace.',
            ]);
        }
    }

    private function notifyInvite(TeamInvitation $invitation): void
    {
        Notification::route('mail', $invitation->email)
            ->notify(new TeamInvitationNotification($invitation));
    }
}
