<?php

namespace App\Filament\App\Resources\TeamInvitationResource\Pages;

use App\Filament\App\Resources\TeamInvitationResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListTeamInvitations extends ListRecords
{
    protected static string $resource = TeamInvitationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('Invite member'),
        ];
    }
}
