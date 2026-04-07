<?php

namespace App\Filament\Admin\Resources\SurveyResponses;

use App\Domain\Feedback\Models\SurveyResponse;
use App\Filament\Admin\Resources\SurveyResponses\Pages\ListSurveyResponses;
use App\Filament\Admin\Resources\SurveyResponses\Pages\ViewSurveyResponse;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SurveyResponseResource extends Resource
{
    protected static ?string $model = SurveyResponse::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Survey Responses';

    protected static string|\UnitEnum|null $navigationGroup = 'Product Management';

    protected static ?int $navigationSort = 8;

    protected static ?string $recordTitleAttribute = 'submitted_at';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Response')
                ->schema([
                    TextEntry::make('survey.title')
                        ->label('Survey'),
                    TextEntry::make('user.email')
                        ->label('User')
                        ->placeholder('Guest'),
                    TextEntry::make('submitted_at')
                        ->dateTime(),
                    TextEntry::make('locale')
                        ->badge()
                        ->placeholder('Unknown'),
                    TextEntry::make('score_summary')
                        ->label('Score')
                        ->state(function (SurveyResponse $record): string {
                            if ($record->max_score === null) {
                                return 'Not scored';
                            }

                            $scorePercent = $record->score_percent !== null
                                ? ' ('.number_format((float) $record->score_percent, 0).'%)'
                                : '';

                            return "{$record->score}/{$record->max_score}{$scorePercent}";
                        }),
                    TextEntry::make('ip_address')
                        ->label('IP address')
                        ->placeholder('Unavailable'),
                    TextEntry::make('user_agent')
                        ->columnSpanFull()
                        ->placeholder('Unavailable'),
                ])
                ->columns(3),
            Section::make('Answers')
                ->schema([
                    TextEntry::make('answers')
                        ->state(function (SurveyResponse $record): string {
                            return collect($record->answers ?? [])
                                ->map(function ($value, $key): string {
                                    $formattedValue = is_array($value) ? implode(', ', $value) : (string) $value;

                                    return "{$key}: {$formattedValue}";
                                })
                                ->implode(PHP_EOL);
                        })
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('survey.title')
                    ->label('Survey')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label('User')
                    ->placeholder('Guest')
                    ->searchable(),
                TextColumn::make('score')
                    ->formatStateUsing(fn ($state, SurveyResponse $record): string => $record->max_score ? "{$record->score}/{$record->max_score}" : '-'),
                TextColumn::make('score_percent')
                    ->label('Score %')
                    ->formatStateUsing(fn ($state): string => $state !== null ? number_format((float) $state, 0).'%' : '-'),
                TextColumn::make('locale')
                    ->badge(),
                TextColumn::make('answers')
                    ->label('Answers')
                    ->formatStateUsing(function ($state): string {
                        $answers = is_array($state) ? $state : [];

                        return collect($answers)
                            ->map(function ($value, $key): string {
                                $formattedValue = is_array($value) ? implode(', ', $value) : (string) $value;

                                return "{$key}: {$formattedValue}";
                            })
                            ->take(3)
                            ->implode(' | ');
                    })
                    ->wrap(),
                TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                ViewAction::make(),
            ])
            ->defaultSort('submitted_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['survey', 'user']);
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
            'index' => ListSurveyResponses::route('/'),
            'view' => ViewSurveyResponse::route('/{record}'),
        ];
    }
}
