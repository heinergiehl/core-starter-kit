<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Audit\Models\ActivityLog;
use App\Filament\Admin\Resources\ActivityLogResource\Pages\ListActivityLogs;
use App\Filament\Admin\Resources\ActivityLogResource\Pages\ViewActivityLog;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ActivityLogResource extends Resource
{
    protected static ?string $model = ActivityLog::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static string|\UnitEnum|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Audit Log';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'event';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Activity')
                ->schema([
                    TextEntry::make('category')->badge(),
                    TextEntry::make('event'),
                    TextEntry::make('actor_id')
                        ->label('Actor')
                        ->formatStateUsing(fn ($state, ActivityLog $record): string => self::actorLabel($record)),
                    TextEntry::make('subject_type')
                        ->label('Subject')
                        ->formatStateUsing(fn ($state, ActivityLog $record): string => self::subjectLabel($record)),
                    TextEntry::make('description')
                        ->placeholder('No description provided.')
                        ->columnSpanFull(),
                    TextEntry::make('ip_address')->label('IP address'),
                    TextEntry::make('created_at')->dateTime(),
                ])
                ->columns(2),
            Section::make('Metadata')
                ->schema([
                    TextEntry::make('metadata')
                        ->formatStateUsing(fn ($state): string => json_encode($state ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('When')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('category')
                    ->badge()
                    ->sortable(),
                TextColumn::make('event')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('actor_id')
                    ->label('Actor')
                    ->formatStateUsing(fn ($state, ActivityLog $record): string => self::actorLabel($record))
                    ->toggleable(),
                TextColumn::make('subject_type')
                    ->label('Subject')
                    ->formatStateUsing(fn ($state, ActivityLog $record): string => self::subjectLabel($record))
                    ->toggleable(),
                TextColumn::make('description')
                    ->limit(80)
                    ->wrap()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['actor', 'subject']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityLogs::route('/'),
            'view' => ViewActivityLog::route('/{record}'),
        ];
    }

    private static function actorLabel(ActivityLog $record): string
    {
        return $record->actor?->name
            ?? $record->actor?->email
            ?? 'System';
    }

    private static function subjectLabel(ActivityLog $record): string
    {
        $subject = $record->subject;

        if ($subject instanceof User) {
            return $subject->name ?: $subject->email;
        }

        if ($subject instanceof Model) {
            if (isset($subject->provider_id) && filled($subject->provider_id)) {
                return class_basename($subject).' '.$subject->provider_id;
            }

            return class_basename($subject).' #'.$subject->getKey();
        }

        if (! $record->subject_type || ! $record->subject_id) {
            return 'None';
        }

        return class_basename((string) $record->subject_type).' #'.$record->subject_id;
    }
}
