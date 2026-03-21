<?php

namespace App\Filament\Admin\Resources\Concerns;

use App\Support\Content\BlogEditorSupport;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\Wizard\Step;
use Filament\Support\Enums\IconPosition;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait InteractsWithBulkTaxonomyActions
{
    /**
     * @param  class-string<Model>  $modelClass
     * @return array<int, Step>
     */
    protected static function makeBulkTaxonomyWizardSteps(
        string $singularLabel,
        string $pluralLabel,
        string $modelClass,
        int $nameMaxLength,
        int $slugMaxLength,
        string $placeholder
    ): array {
        return [
            Step::make('Paste')
                ->description("Paste comma-separated or one-per-line {$pluralLabel}.")
                ->afterValidation(function (Get $get, Set $set): void {
                    $set(
                        'drafts',
                        collect(BlogEditorSupport::prepareTaxonomyDraftsFromBulkInput((string) $get('names')))
                            ->map(fn (array $draft): array => [
                                ...$draft,
                                'slug_sync_enabled' => true,
                            ])
                            ->all(),
                    );
                })
                ->schema([
                    Textarea::make('names')
                        ->label(Str::headline($pluralLabel))
                        ->required()
                        ->rows(6)
                        ->placeholder($placeholder)
                        ->helperText('Comma, semicolon, or one-per-line all work. The preview is generated on the next step.')
                        ->rule(function () use ($singularLabel): Closure {
                            return function (string $attribute, mixed $value, Closure $fail) use ($singularLabel): void {
                                if (BlogEditorSupport::prepareTaxonomyDraftsFromBulkInput((string) $value) !== []) {
                                    return;
                                }

                                $fail("Paste at least one {$singularLabel}.");
                            };
                        }),
                ]),
            Step::make('Review')
                ->description('Edit anything that needs cleanup. Exact matches are reused automatically.')
                ->schema([
                    Placeholder::make('review_summary')
                        ->hiddenLabel()
                        ->html()
                        ->content(fn (Get $get) => BlogEditorSupport::renderTaxonomyDraftSummary(
                            drafts: (array) ($get('drafts') ?? []),
                            modelClass: $modelClass,
                            singularLabel: $singularLabel,
                        ))
                        ->columnSpanFull(),
                    Placeholder::make('review_guidance')
                        ->hiddenLabel()
                        ->html()
                        ->content(BlogEditorSupport::renderTaxonomyDraftReviewGuidance())
                        ->columnSpanFull(),
                    Repeater::make('drafts')
                        ->label(Str::headline($pluralLabel))
                        ->table([
                            TableColumn::make('Name')
                                ->markAsRequired(),
                            TableColumn::make('Slug')
                                ->markAsRequired(),
                            TableColumn::make('Slug Mode'),
                            TableColumn::make('Status'),
                        ])
                        ->compact()
                        ->addable(false)
                        ->reorderable(false)
                        ->minItems(1)
                        ->helperText('Exact matches are reused. Conflict rows need a different slug before you can save.')
                        ->columnSpanFull()
                        ->schema([
                            static::makeSlugSyncHiddenField(),
                            static::configureBulkTaxonomyNameField(
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength($nameMaxLength),
                            ),
                            static::configureBulkTaxonomySlugField(
                                TextInput::make('slug')
                                    ->required()
                                    ->alphaDash()
                                    ->maxLength($slugMaxLength),
                            ),
                            Placeholder::make('slug_sync_state')
                                ->label('Slug Mode')
                                ->content(fn (Placeholder $component, Get $get) => BlogEditorSupport::renderSlugSyncState(
                                    sourceStatePath: $component->resolveRelativeStatePath('../name'),
                                    syncEnabled: (bool) $get('slug_sync_enabled'),
                                ))
                                ->html(),
                            Placeholder::make('status')
                                ->label('Status')
                                ->content(fn (Get $get): string => BlogEditorSupport::getTaxonomyDraftStatusMeta(
                                    name: $get('name'),
                                    slug: $get('slug'),
                                    drafts: (array) ($get('../../drafts') ?? []),
                                    modelClass: $modelClass,
                                )['label'])
                                ->badge()
                                ->color(fn (string $state): string => match ($state) {
                                    'Create new' => 'success',
                                    'Reuse existing' => 'info',
                                    'Needs attention' => 'warning',
                                    default => 'danger',
                                })
                                ->icon(fn (string $state): string => match ($state) {
                                    'Create new' => 'heroicon-m-plus-circle',
                                    'Reuse existing' => 'heroicon-m-arrow-path',
                                    'Needs attention' => 'heroicon-m-pencil-square',
                                    'Conflict in list' => 'heroicon-m-no-symbol',
                                    default => 'heroicon-m-exclamation-triangle',
                                })
                                ->iconPosition(IconPosition::Before),
                        ])
                        ->rule(function () use ($modelClass, $singularLabel): Closure {
                            return function (string $attribute, mixed $value, Closure $fail) use ($modelClass, $singularLabel): void {
                                BlogEditorSupport::validateTaxonomyDrafts(
                                    drafts: (array) $value,
                                    modelClass: $modelClass,
                                    singularLabel: $singularLabel,
                                    errorPathPrefix: $attribute,
                                );
                            };
                        }),
                ]),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $mountedActions
     */
    protected static function getMountedActionDataPath(array $mountedActions, string $field): string
    {
        $mountedActionIndex = array_key_last($mountedActions);

        if (! is_int($mountedActionIndex)) {
            return $field;
        }

        return "mountedActions.{$mountedActionIndex}.data.{$field}";
    }

    /**
     * @param  array{created:int,existing:int,ids:array<int, int>}  $result
     */
    protected static function formatTaxonomyNotificationBody(string $singularLabel, array $result, bool $selected = false): string
    {
        $count = count($result['ids']);
        $itemLabel = Str::plural($singularLabel, $count);
        $status = "{$count} {$itemLabel} ready: {$result['created']} created, {$result['existing']} reused.";

        if (! $selected) {
            return $status;
        }

        return "{$status} Selected automatically.";
    }

    protected static function configureBulkTaxonomyNameField(
        TextInput $field,
        string $slugField = 'slug',
        string $syncField = 'slug_sync_enabled'
    ): TextInput {
        return $field
            ->live(onBlur: true)
            ->afterStateUpdated(function (?string $state, Get $get, Set $set) use ($slugField, $syncField): void {
                if (! (bool) $get($syncField)) {
                    return;
                }

                $set($slugField, BlogEditorSupport::syncSlugFromSource($state));
            });
    }

    protected static function configureBulkTaxonomySlugField(
        TextInput $field,
        string $sourceField = 'name',
        string $syncField = 'slug_sync_enabled'
    ): TextInput {
        return $field
            ->live(onBlur: true)
            ->afterStateHydrated(function (?string $state, Get $get, Set $set) use ($sourceField, $syncField): void {
                $slugState = BlogEditorSupport::initializeSlugEditorState($state, $get($sourceField));

                if ($slugState['slug'] !== (string) $state) {
                    $set('slug', $slugState['slug']);
                }

                if ($get($syncField) === null) {
                    $set($syncField, $slugState['sync']);
                }
            })
            ->afterStateUpdated(function (?string $state, Get $get, Set $set) use ($sourceField, $syncField): void {
                $slugState = BlogEditorSupport::updateSlugEditorState($state, $get($sourceField));

                if ($slugState['slug'] !== (string) $state) {
                    $set('slug', $slugState['slug']);
                }

                $set($syncField, $slugState['sync']);
            })
            ->dehydrateStateUsing(fn (?string $state): string => BlogEditorSupport::normalizeSlugInput($state))
            ->hint(fn (TextInput $component, Get $get) => BlogEditorSupport::renderSlugSyncState(
                sourceStatePath: $component->resolveRelativeStatePath($sourceField),
                syncEnabled: (bool) $get($syncField),
            ))
            ->hintColor(fn (Get $get): string => (bool) $get($syncField) ? 'success' : 'gray')
            ->hintIcon(fn (Get $get): string => (bool) $get($syncField) ? 'heroicon-m-link' : 'heroicon-m-pencil-square')
            ->suffixAction(static::makeResetBulkTaxonomySlugAction($sourceField, $syncField), isInline: true)
            ->helperText(fn (Get $get): string => BlogEditorSupport::describeSlugBehavior(
                sourceValue: $get($sourceField),
                syncEnabled: (bool) $get($syncField),
            ));
    }

    protected static function makeResetBulkTaxonomySlugAction(
        string $sourceField = 'name',
        string $syncField = 'slug_sync_enabled'
    ): Action {
        return Action::make(Str::camel("reset bulk {$sourceField} {$syncField} slug"))
            ->label('Reset')
            ->link()
            ->icon('heroicon-o-arrow-path')
            ->tooltip('Use the generated slug again')
            ->visible(fn (Get $get): bool => filled(BlogEditorSupport::generateSlug($get($sourceField))) && (! (bool) $get($syncField)))
            ->action(function (Get $get, Set $set) use ($sourceField, $syncField): void {
                $slugState = BlogEditorSupport::initializeSlugEditorState(null, $get($sourceField));

                $set('slug', $slugState['slug']);
                $set($syncField, $slugState['sync']);
            });
    }
}
