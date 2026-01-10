<?php

namespace App\Filament\App\Resources\TeamInvitationResource\Pages;

use App\Domain\Organization\Enums\TeamRole;
use App\Domain\Organization\Services\TeamInvitationService;
use App\Filament\App\Resources\TeamInvitationResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class CreateTeamInvitation extends CreateRecord
{
    protected static string $resource = TeamInvitationResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = auth()->user();
        $team = $user?->currentTeam;

        if (!$user || !$team) {
            throw ValidationException::withMessages([
                'email' => 'Select a workspace before inviting members.',
            ]);
        }

        $role = TeamRole::tryFrom($data['role'] ?? TeamRole::Member->value) ?? TeamRole::Member;

        return app(TeamInvitationService::class)
            ->createInvitation($team, $user, $data['email'], $role);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
