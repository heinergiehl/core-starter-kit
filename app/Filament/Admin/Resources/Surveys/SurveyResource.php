<?php

namespace App\Filament\Admin\Resources\Surveys;

use App\Domain\Feedback\Models\Survey;
use App\Enums\SurveyQuestionType;
use App\Enums\SurveyStatus;
use App\Filament\Admin\Resources\Surveys\Pages\CreateSurvey;
use App\Filament\Admin\Resources\Surveys\Pages\EditSurvey;
use App\Filament\Admin\Resources\Surveys\Pages\ListSurveys;
use App\Filament\Admin\Resources\Surveys\Pages\ViewSurvey;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SurveyResource extends Resource
{
    protected static ?string $model = Survey::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Surveys';

    protected static string|\UnitEnum|null $navigationGroup = 'Product Management';

    protected static ?int $navigationSort = 7;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Survey')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                if (filled($get('slug'))) {
                                    return;
                                }

                                $set('slug', Str::slug((string) $state));
                            }),
                        TextInput::make('slug')
                            ->maxLength(255)
                            ->helperText('Leave empty to auto-generate from the title.'),
                        Select::make('status')
                            ->options(SurveyStatus::class)
                            ->required()
                            ->default(SurveyStatus::Draft->value),
                        Toggle::make('is_public')
                            ->label('Public')
                            ->default(false),
                        Toggle::make('requires_auth')
                            ->label('Requires sign-in')
                            ->default(false),
                        Toggle::make('allow_multiple_submissions')
                            ->label('Allow multiple submissions')
                            ->default(false),
                        TextInput::make('submit_label')
                            ->label('Submit button label')
                            ->maxLength(60)
                            ->default('Submit'),
                        TextInput::make('success_title')
                            ->maxLength(120),
                        Textarea::make('description')
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('success_message')
                            ->rows(3)
                            ->columnSpanFull(),
                        DateTimePicker::make('starts_at'),
                        DateTimePicker::make('ends_at'),
                    ])
                    ->columns(2),
                Section::make('Questions')
                    ->schema([
                        Repeater::make('questions')
                            ->defaultItems(1)
                            ->addActionLabel('Add question')
                            ->cloneable()
                            ->collapsed()
                            ->itemLabel(fn (array $state): ?string => $state['label'] ?? $state['key'] ?? 'Question')
                            ->schema([
                                Grid::make(3)->schema([
                                    TextInput::make('label')
                                        ->required()
                                        ->maxLength(255)
                                        ->live(onBlur: true)
                                        ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                            if (filled($get('key'))) {
                                                return;
                                            }

                                            $set('key', Str::slug((string) $state, '_'));
                                        }),
                                    TextInput::make('key')
                                        ->required()
                                        ->maxLength(64)
                                        ->rules(['alpha_dash'])
                                        ->helperText('Stable answer key, e.g. nps or use_case.'),
                                    Select::make('type')
                                        ->options(SurveyQuestionType::class)
                                        ->required()
                                        ->live(),
                                ]),
                                Grid::make(3)->schema([
                                    Toggle::make('required')
                                        ->default(false),
                                    TextInput::make('weight')
                                        ->label('Score weight')
                                        ->numeric()
                                        ->default(1)
                                        ->minValue(0)
                                        ->visible(fn (Get $get): bool => in_array($get('type'), self::scorableQuestionTypes(), true))
                                        ->helperText('Use 0 to keep the answer but exclude it from total scoring.'),
                                    TextInput::make('placeholder')
                                        ->maxLength(255)
                                        ->visible(fn (Get $get): bool => in_array($get('type'), self::textQuestionTypes(), true)),
                                ]),
                                Textarea::make('help_text')
                                    ->rows(2)
                                    ->columnSpanFull(),
                                Grid::make(4)->schema([
                                    TextInput::make('min_value')
                                        ->numeric()
                                        ->default(fn (Get $get) => $get('type') === SurveyQuestionType::Nps->value ? 0 : 1)
                                        ->visible(fn (Get $get): bool => in_array($get('type'), self::rangeQuestionTypes(), true)),
                                    TextInput::make('max_value')
                                        ->numeric()
                                        ->default(fn (Get $get) => $get('type') === SurveyQuestionType::Nps->value ? 10 : 5)
                                        ->visible(fn (Get $get): bool => in_array($get('type'), self::rangeQuestionTypes(), true)),
                                    TextInput::make('min_label')
                                        ->maxLength(120)
                                        ->visible(fn (Get $get): bool => in_array($get('type'), self::rangeQuestionTypes(), true)),
                                    TextInput::make('max_label')
                                        ->maxLength(120)
                                        ->visible(fn (Get $get): bool => in_array($get('type'), self::rangeQuestionTypes(), true)),
                                ]),
                                Grid::make(2)->schema([
                                    TextInput::make('yes_score')
                                        ->numeric()
                                        ->default(1)
                                        ->visible(fn (Get $get): bool => $get('type') === SurveyQuestionType::YesNo->value),
                                    TextInput::make('no_score')
                                        ->numeric()
                                        ->default(0)
                                        ->visible(fn (Get $get): bool => $get('type') === SurveyQuestionType::YesNo->value),
                                ]),
                                Repeater::make('options')
                                    ->addActionLabel('Add option')
                                    ->collapsed()
                                    ->defaultItems(2)
                                    ->visible(fn (Get $get): bool => in_array($get('type'), self::choiceQuestionTypes(), true))
                                    ->schema([
                                        Grid::make(3)->schema([
                                            TextInput::make('label')
                                                ->required()
                                                ->maxLength(120)
                                                ->live(onBlur: true)
                                                ->afterStateUpdated(function (?string $state, Get $get, Set $set): void {
                                                    if (filled($get('value'))) {
                                                        return;
                                                    }

                                                    $set('value', Str::slug((string) $state, '_'));
                                                }),
                                            TextInput::make('value')
                                                ->required()
                                                ->maxLength(120)
                                                ->rules(['alpha_dash']),
                                            TextInput::make('score')
                                                ->numeric()
                                                ->default(0),
                                        ]),
                                    ]),
                            ]),
                    ]),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Survey')
                ->schema([
                    TextEntry::make('title'),
                    TextEntry::make('slug'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('responses_count')
                        ->label('Responses'),
                    TextEntry::make('is_public')
                        ->label('Public')
                        ->badge()
                        ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No'),
                    TextEntry::make('requires_auth')
                        ->label('Requires sign-in')
                        ->badge()
                        ->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : 'No'),
                    TextEntry::make('allow_multiple_submissions')
                        ->label('Multiple submissions')
                        ->badge()
                        ->formatStateUsing(fn (bool $state): string => $state ? 'Allowed' : 'One response'),
                    TextEntry::make('starts_at')->dateTime(),
                    TextEntry::make('ends_at')->dateTime(),
                    TextEntry::make('public_url')
                        ->label('Public URL')
                        ->state(fn (Survey $record): string => route('surveys.show', [
                            'locale' => (string) config('saas.locales.default', config('app.locale', 'en')),
                            'survey' => $record,
                        ]))
                        ->copyable()
                        ->url(fn (string $state): string => $state, shouldOpenInNewTab: true)
                        ->columnSpanFull(),
                ])
                ->columns(3),
            Section::make('Messages')
                ->schema([
                    TextEntry::make('description')
                        ->placeholder('No description provided.')
                        ->columnSpanFull(),
                    TextEntry::make('submit_label')
                        ->label('Submit button'),
                    TextEntry::make('success_title'),
                    TextEntry::make('success_message')
                        ->placeholder('No success message provided.')
                        ->columnSpanFull(),
                ])
                ->columns(2),
            Section::make('Questions')
                ->schema([
                    TextEntry::make('questions')
                        ->label('Question definition')
                        ->formatStateUsing(fn ($state): string => json_encode($state ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean(),
                IconColumn::make('requires_auth')
                    ->label('Auth')
                    ->boolean(),
                TextColumn::make('responses_count')
                    ->label('Responses')
                    ->counts('responses')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount('responses');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSurveys::route('/'),
            'create' => CreateSurvey::route('/create'),
            'view' => ViewSurvey::route('/{record}'),
            'edit' => EditSurvey::route('/{record}/edit'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function choiceQuestionTypes(): array
    {
        return [
            SurveyQuestionType::SingleChoice->value,
            SurveyQuestionType::MultipleChoice->value,
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function rangeQuestionTypes(): array
    {
        return [
            SurveyQuestionType::Rating->value,
            SurveyQuestionType::Nps->value,
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function scorableQuestionTypes(): array
    {
        return [
            SurveyQuestionType::Rating->value,
            SurveyQuestionType::Nps->value,
            SurveyQuestionType::YesNo->value,
            SurveyQuestionType::SingleChoice->value,
            SurveyQuestionType::MultipleChoice->value,
        ];
    }

    /**
     * @return array<int, string>
     */
    private static function textQuestionTypes(): array
    {
        return [
            SurveyQuestionType::ShortText->value,
            SurveyQuestionType::LongText->value,
        ];
    }
}
