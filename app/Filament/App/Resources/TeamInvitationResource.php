<?php

namespace App\Filament\App\Resources;

use App\Domain\Organization\Models\TeamInvitation;
use App\Filament\App\Resources\TeamInvitationResource\Pages\CreateTeamInvitation;
use App\Filament\App\Resources\TeamInvitationResource\Pages\ListTeamInvitations;
use Filament\Actions\DeleteAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use App\Domain\Organization\Enums\TeamRole;

class TeamInvitationResource extends Resource
{
    protected static ?string $model = TeamInvitation::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static string | \UnitEnum | null $navigationGroup = 'Workspace';

    protected static ?string $navigationLabel = 'Invitations';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('role')
                    ->options([
                        'admin' => 'Admin',
                        'billing' => 'Billing',
                        'member' => 'Member',
                    ])
                    ->required()
                    ->default('member'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('email')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->state(function (TeamInvitation $record): string {
                        if ($record->accepted_at) {
                            return 'accepted';
                        }

                        if ($record->expires_at && $record->expires_at->isPast()) {
                            return 'expired';
                        }

                        return 'pending';
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'accepted' => 'success',
                        'expired' => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('accepted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('token')
                    ->copyable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                DeleteAction::make()
                    ->visible(fn (TeamInvitation $record): bool => !$record->accepted_at),
            ])
            ->filters([
                Tables\Filters\Filter::make('pending')
                    ->label('Pending')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNull('accepted_at')
                        ->where(function (Builder $query): Builder {
                            return $query->whereNull('expires_at')
                                ->orWhere('expires_at', '>', now());
                        })),
                Tables\Filters\Filter::make('expired')
                    ->label('Expired')
                    ->query(fn (Builder $query): Builder => $query
                        ->whereNull('accepted_at')
                        ->whereNotNull('expires_at')
                        ->where('expires_at', '<=', now())),
                Tables\Filters\Filter::make('accepted')
                    ->label('Accepted')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('accepted_at')),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $teamId = auth()->user()?->current_team_id;

        if (!$teamId) {
            return parent::getEloquentQuery()->whereRaw('1 = 0');
        }

        return parent::getEloquentQuery()->where('team_id', $teamId);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTeamInvitations::route('/'),
            'create' => CreateTeamInvitation::route('/create'),
        ];
    }

    public static function canCreate(): bool
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
}
