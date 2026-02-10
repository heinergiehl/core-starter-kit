<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Identity\Services\ImpersonationService;
use App\Filament\Admin\Resources\UserResource\Pages\CreateUser;
use App\Filament\Admin\Resources\UserResource\Pages\EditUser;
use App\Filament\Admin\Resources\UserResource\Pages\ListUsers;
use App\Models\User;
use App\Support\Authorization\PermissionGuardrails;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user';

    protected static string|\UnitEnum|null $navigationGroup = 'User Management';

    protected static ?string $navigationLabel = 'Users';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                TextInput::make('password')
                    ->password()
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText('Leave blank to keep the current password.'),
                Toggle::make('is_admin')
                    ->label('Admin access')
                    ->disabled(fn (?User $record): bool => PermissionGuardrails::isLastAdminUser($record)),
                Select::make('roles')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable(),
                Select::make('permissions')
                    ->relationship('permissions', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->helperText('Direct permissions override role defaults.'),
                DateTimePicker::make('email_verified_at')
                    ->label('Email verified at'),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('is_admin')
                    ->label('Admin')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
                TextColumn::make('roles.name')
                    ->label('Roles')
                    ->badge()
                    ->separator(', ')
                    ->toggleable(),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('impersonate')
                    ->label('Impersonate')
                    ->icon('heroicon-o-identification')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Impersonate User')
                    ->modalDescription(fn (User $record) => "You are about to log in as {$record->name}. You can return to your account anytime.")
                    ->modalSubmitActionLabel('Start Impersonating')
                    ->closeModalByClickingAway(false)
                    ->action(function (User $record) {
                        Gate::authorize('impersonate', $record);

                        $impersonator = auth()->user();

                        if (! $impersonator) {
                            abort(403);
                        }

                        $service = app(ImpersonationService::class);
                        $success = $service->impersonate($impersonator, $record);

                        if (! $success) {
                            Notification::make()
                                ->title('Unable to impersonate this user.')
                                ->danger()
                                ->send();

                            return null;
                        }

                        return redirect()->route('dashboard')
                            ->with('success', "Now impersonating {$record->name}");
                    })
                    ->visible(fn (User $record) => ! $record->is_admin && auth()->id() !== $record->id),
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (User $record): bool => PermissionGuardrails::isLastAdminUser($record)),
            ]);
    }

    public static function canDelete(Model $record): bool
    {
        if ($record instanceof User && PermissionGuardrails::isLastAdminUser($record)) {
            return false;
        }

        return parent::canDelete($record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
