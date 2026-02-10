<?php

namespace App\Filament\Admin\Resources;

use App\Filament\Admin\Resources\PermissionResource\Pages\CreatePermission;
use App\Filament\Admin\Resources\PermissionResource\Pages\EditPermission;
use App\Filament\Admin\Resources\PermissionResource\Pages\ListPermissions;
use App\Support\Authorization\PermissionGuardrails;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Spatie\Permission\Models\Permission;

class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-key';

    protected static string|\UnitEnum|null $navigationGroup = 'User Management';

    protected static ?string $navigationLabel = 'Permissions';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(150)
                    ->unique(ignoreRecord: true)
                    ->disabled(fn (?Permission $record): bool => PermissionGuardrails::isProtectedPermission($record)),
                TextInput::make('guard_name')
                    ->label('Guard')
                    ->default(PermissionGuardrails::guardName())
                    ->disabled()
                    ->dehydrated(),
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
                TextColumn::make('guard_name')
                    ->label('Guard')
                    ->badge()
                    ->sortable(),
                TextColumn::make('roles_count')
                    ->counts('roles')
                    ->label('Roles')
                    ->sortable(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->defaultSort('name')
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->hidden(fn (Permission $record): bool => PermissionGuardrails::isProtectedPermission($record)),
            ]);
    }

    public static function canDelete(Model $record): bool
    {
        if ($record instanceof Permission && PermissionGuardrails::isProtectedPermission($record)) {
            return false;
        }

        return parent::canDelete($record);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPermissions::route('/'),
            'create' => CreatePermission::route('/create'),
            'edit' => EditPermission::route('/{record}/edit'),
        ];
    }
}
