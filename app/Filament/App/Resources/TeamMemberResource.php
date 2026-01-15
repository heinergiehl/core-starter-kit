<?php

namespace App\Filament\App\Resources;

use App\Domain\Organization\Models\TeamMembership;
use App\Domain\Organization\Enums\TeamRole;
use App\Filament\App\Resources\TeamMemberResource\Pages\EditTeamMember;
use App\Filament\App\Resources\TeamMemberResource\Pages\ListTeamMembers;
use App\Jobs\SyncSeatQuantityJob;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TeamMemberResource extends Resource
{
    protected static ?string $model = TeamMembership::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-users';

    protected static string | \UnitEnum | null $navigationGroup = 'Workspace';

    protected static ?string $navigationLabel = 'Members';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\Select::make('role')
                    ->options([
                        TeamRole::Admin->value => 'Admin',
                        TeamRole::Billing->value => 'Billing',
                        TeamRole::Member->value => 'Member',
                    ])
                    ->disabled(fn (?TeamMembership $record): bool => $record?->team?->owner_id === $record?->user_id),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Name')
                    ->searchable(),
                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('joined_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('joined_at', 'desc')
            ->actions([
                EditAction::make()
                    ->visible(fn (TeamMembership $record): bool => $record->team?->owner_id !== $record->user_id),
                DeleteAction::make()
                    ->visible(fn (TeamMembership $record): bool => $record->team?->owner_id !== $record->user_id)
                    ->after(function (TeamMembership $record): void {
                        SyncSeatQuantityJob::dispatch($record->team_id);
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->after(function (): void {
                            $teamId = auth()->user()?->current_team_id;
                            if ($teamId) {
                                SyncSeatQuantityJob::dispatch($teamId);
                            }
                        }),
                ]),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $teamId = auth()->user()?->current_team_id;

        if (!$teamId) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()
            ->where('team_id', $teamId)
            ->with(['user', 'team']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return static::canManageTeam();
    }

    public static function canDelete(Model $record): bool
    {
        return static::canManageTeam();
    }

    private static function canManageTeam(): bool
    {
        $user = auth()->user();
        $team = $user?->currentTeam;

        if (!$user || !$team) {
            return false;
        }

        return $team->isOwner($user) || $team->hasRole($user, TeamRole::Admin);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeamMembers::route('/'),
            'edit' => EditTeamMember::route('/{record}/edit'),
        ];
    }
}
