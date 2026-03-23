<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Content\Models\BlogCategory;
use App\Domain\Content\Models\BlogPost;
use App\Domain\Content\Models\BlogTag;
use App\Enums\Locale;
use App\Enums\PostStatus;
use App\Filament\Admin\Resources\Concerns\InteractsWithAutoSlugFields;
use App\Filament\Admin\Resources\Concerns\InteractsWithBulkTaxonomyActions;
use App\Support\Content\BlogEditorSupport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class BlogPostResource extends Resource
{
    use InteractsWithAutoSlugFields;
    use InteractsWithBulkTaxonomyActions;

    protected static ?string $model = BlogPost::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Blog Posts';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Source')
                    ->visible(fn (?BlogPost $record): bool => filled($record) && $record->isManagedByMarkdown())
                    ->schema([
                        Placeholder::make('content_source_meta')
                            ->hiddenLabel()
                            ->html()
                            ->content(fn (?BlogPost $record) => static::renderContentSourceMeta($record)),
                    ])
                    ->columnSpanFull(),

                Section::make('Translations')
                    ->visible(fn (?BlogPost $record): bool => filled($record))
                    ->schema([
                        Placeholder::make('translation_family')
                            ->hiddenLabel()
                            ->html()
                            ->content(fn (?BlogPost $record) => static::renderTranslationFamily($record)),
                    ])
                    ->columnSpanFull(),

                Section::make('Content')
                    ->schema([
                        static::makeSlugSyncHiddenField('slug_sync_enabled'),

                        Select::make('locale')
                            ->label('Locale')
                            ->options(static::localeOptions())
                            ->default((string) config('saas.locales.default', config('app.locale', 'en')))
                            ->required()
                            ->native(false)
                            ->helperText('Each translation keeps its own locale, slug, SEO fields, and publish state.')
                            ->disabled(fn (string $operation): bool => $operation === 'edit')
                            ->dehydrated(fn (string $operation): bool => $operation === 'create'),

                        static::configureSlugSourceField(
                            TextInput::make('title')
                                ->label('Title')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('Stripe vs Paddle for SaaS billing')
                                ->helperText('Main headline for the post and the default SEO title.'),
                            syncField: 'slug_sync_enabled',
                        ),

                        static::configureSlugField(
                            TextInput::make('slug')
                                ->label('URL Slug')
                                ->required()
                                ->maxLength(255)
                                ->rules([
                                    fn (Get $get, ?BlogPost $record) => Rule::unique('blog_posts', 'slug')
                                        ->where('locale', $get('locale'))
                                        ->ignore($record?->id),
                                ])
                                ->rules(['alpha_dash'])
                                ->placeholder('stripe-vs-paddle-for-saas-billing'),
                            sourceField: 'title',
                            sourceLabel: 'title',
                            syncField: 'slug_sync_enabled',
                        ),

                        Textarea::make('excerpt')
                            ->label('Summary')
                            ->rows(3)
                            ->maxLength(500)
                            ->placeholder('Short summary for blog cards, RSS, and the default SEO description.')
                            ->helperText('Recommended. Blank SEO description will reuse this.')
                            ->live(onBlur: true)
                            ->columnSpanFull(),

                        RichEditor::make('body_html')
                            ->label('Content')
                            ->required()
                            ->helperText('Write the full post content. Markdown fallback is still supported in the public renderer.')
                            ->fileAttachmentsDirectory('blog-attachments')
                            ->fileAttachmentsVisibility('public')
                            ->resizableImages()
                            ->toolbarButtons([
                                'attachFiles',
                                'blockquote',
                                'bold',
                                'bulletList',
                                'codeBlock',
                                'h2',
                                'h3',
                                'italic',
                                'link',
                                'orderedList',
                                'redo',
                                'strike',
                                'underline',
                                'undo',
                            ])
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Organization')
                    ->schema([
                        FileUpload::make('featured_image')
                            ->label('Featured Image')
                            ->image()
                            ->helperText('Shown in cards and social previews.')
                            ->directory('blog-images')
                            ->imageResizeMode('cover')
                            ->imageCropAspectRatio('16:9')
                            ->imageResizeTargetWidth('1200')
                            ->imageResizeTargetHeight('675'),

                        Select::make('author_id')
                            ->label('Author')
                            ->relationship('author', 'name')
                            ->searchable()
                            ->preload()
                            ->default(auth()->id())
                            ->helperText('Defaults to the signed-in admin. Public byline, avatar, and bio come from the selected user profile.'),

                        Select::make('category_id')
                            ->label('Primary Category')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Use one primary category per post. Paste list can bulk-create options here.')
                            ->hintAction(static::makeBulkCreateCategoriesAction())
                            ->createOptionForm([
                                static::makeSlugSyncHiddenField(),
                                static::configureSlugSourceField(
                                    TextInput::make('name')
                                        ->required()
                                        ->maxLength(100),
                                ),
                                static::configureSlugField(
                                    TextInput::make('slug')
                                        ->required()
                                        ->alphaDash()
                                        ->maxLength(100)
                                        ->unique('blog_categories', 'slug'),
                                    sourceLabel: 'category name',
                                ),
                            ]),

                        Select::make('tags')
                            ->label('Tags')
                            ->relationship('tags', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Secondary topics. Paste list creates and selects multiple tags in one step.')
                            ->hintAction(static::makeBulkCreateTagsAction())
                            ->createOptionForm([
                                static::makeSlugSyncHiddenField(),
                                static::configureSlugSourceField(
                                    TextInput::make('name')
                                        ->required()
                                        ->maxLength(50),
                                ),
                                static::configureSlugField(
                                    TextInput::make('slug')
                                        ->required()
                                        ->alphaDash()
                                        ->maxLength(50)
                                        ->unique('blog_tags', 'slug'),
                                    sourceLabel: 'tag name',
                                ),
                            ]),
                    ]),

                Section::make('Publishing')
                    ->schema([
                        ToggleButtons::make('status')
                            ->label('Status')
                            ->inline()
                            ->options(PostStatus::class)
                            ->default(PostStatus::Draft)
                            ->helperText('Draft = private, Published = live, Archived = hidden.')
                            ->live()
                            ->required(),

                        DateTimePicker::make('published_at')
                            ->label('Publish On')
                            ->helperText(function (Get $get): string {
                                return static::isPublishedState($get('status'))
                                    ? 'Leave empty to publish now.'
                                    : 'Optional until you publish.';
                            }),
                    ])
                    ->columnSpan(1),

                Section::make('Search Appearance')
                    ->description('Optional. Leave blank to reuse the Title and Summary above.')
                    ->schema([
                        TextInput::make('meta_title')
                            ->label('SEO Title')
                            ->maxLength(60)
                            ->placeholder(fn (Get $get): string => (string) ($get('title') ?: 'Uses the title above'))
                            ->helperText(function (Get $get): string {
                                $state = (string) ($get('meta_title') ?? '');
                                $length = Str::length($state);
                                $fallback = filled($state) ? '' : ' Blank = Title.';

                                return "{$length}/60.{$fallback}";
                            })
                            ->live(onBlur: true),

                        Textarea::make('meta_description')
                            ->label('SEO Description')
                            ->rows(3)
                            ->maxLength(160)
                            ->placeholder(fn (Get $get): string => (string) ($get('excerpt') ?: 'Uses the summary above'))
                            ->helperText(function (Get $get): string {
                                $state = (string) ($get('meta_description') ?? '');
                                $length = Str::length($state);
                                $fallback = filled($state) ? '' : ' Blank = Summary.';

                                return "{$length}/160.{$fallback}";
                            })
                            ->live(onBlur: true)
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->collapsible()
                    ->collapsed()
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('translations'))
            ->columns([
                ImageColumn::make('featured_image')
                    ->label('Image')
                    ->circular()
                    ->size(40),

                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->description(fn (BlogPost $record): string => $record->translationStatusSummary()),

                TextColumn::make('content_source')
                    ->label('Source')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => $state === 'markdown' ? 'Markdown' : 'Manual')
                    ->color(fn (?string $state): string => $state === 'markdown' ? 'info' : 'gray'),

                TextColumn::make('locale')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => static::localeOptions()[$state] ?? strtoupper($state))
                    ->sortable(),

                TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                TextColumn::make('author.name')
                    ->label('Author')
                    ->sortable(),

                TextColumn::make('status')
                    ->badge()
                    ->sortable(),

                TextColumn::make('published_at')
                    ->label('Date')
                    ->dateTime('M d, Y')
                    ->sortable(),

                TextColumn::make('reading_time')
                    ->label('Read Time')
                    ->suffix(' min')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('locale')
                    ->options(static::localeOptions()),

                SelectFilter::make('category')
                    ->relationship('category', 'name'),

                SelectFilter::make('status')
                    ->options(\App\Enums\PostStatus::class),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Admin\Resources\BlogPostResource\Pages\ListBlogPosts::route('/'),
            'create' => \App\Filament\Admin\Resources\BlogPostResource\Pages\CreateBlogPost::route('/create'),
            'edit' => \App\Filament\Admin\Resources\BlogPostResource\Pages\EditBlogPost::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::count();
    }

    public static function localeOptions(): array
    {
        return collect(Locale::cases())
            ->mapWithKeys(fn (Locale $locale): array => [$locale->value => $locale->getLabel() ?? strtoupper($locale->value)])
            ->all();
    }

    protected static function isPublishedState(mixed $value): bool
    {
        if ($value instanceof PostStatus) {
            return $value === PostStatus::Published;
        }

        if (is_string($value)) {
            return PostStatus::tryFrom($value) === PostStatus::Published;
        }

        return false;
    }

    protected static function makeBulkCreateCategoriesAction(): Action
    {
        return Action::make('pasteCategories')
            ->label('Paste list')
            ->link()
            ->color('gray')
            ->icon('heroicon-o-clipboard-document-list')
            ->modalWidth('2xl')
            ->modalHeading('Bulk create categories')
            ->modalDescription('Paste names, review the generated slugs, then confirm.')
            ->modalSubmitActionLabel('Create categories')
            ->steps(static::makeBulkTaxonomyWizardSteps(
                singularLabel: 'category',
                pluralLabel: 'categories',
                modelClass: BlogCategory::class,
                nameMaxLength: 100,
                slugMaxLength: 100,
                placeholder: "Billing\nProduct Updates\nGrowth",
            ))
            ->action(function (array $data, Set $schemaSet, array $mountedActions): void {
                $result = BlogEditorSupport::commitTaxonomyDrafts(
                    drafts: (array) ($data['drafts'] ?? []),
                    modelClass: BlogCategory::class,
                    singularLabel: 'category',
                    errorPathPrefix: static::getMountedActionDataPath($mountedActions, 'drafts'),
                );

                if (count($result['ids']) === 1) {
                    $schemaSet('category_id', $result['ids'][0]);

                    Notification::make()
                        ->title('Category ready')
                        ->body(static::formatTaxonomyNotificationBody('category', $result, true))
                        ->success()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Categories ready')
                    ->body(static::formatTaxonomyNotificationBody('category', $result))
                    ->success()
                    ->send();
            });
    }

    protected static function makeBulkCreateTagsAction(): Action
    {
        return Action::make('pasteTags')
            ->label('Paste list')
            ->link()
            ->color('gray')
            ->icon('heroicon-o-clipboard-document-list')
            ->modalWidth('2xl')
            ->modalHeading('Bulk create tags')
            ->modalDescription('Paste names, review the generated slugs, then confirm.')
            ->modalSubmitActionLabel('Create and select tags')
            ->steps(static::makeBulkTaxonomyWizardSteps(
                singularLabel: 'tag',
                pluralLabel: 'tags',
                modelClass: BlogTag::class,
                nameMaxLength: 50,
                slugMaxLength: 50,
                placeholder: 'laravel saas starter kit, filament, stripe',
            ))
            ->action(function (array $data, Get $schemaGet, Set $schemaSet, array $mountedActions): void {
                $result = BlogEditorSupport::commitTaxonomyDrafts(
                    drafts: (array) ($data['drafts'] ?? []),
                    modelClass: BlogTag::class,
                    singularLabel: 'tag',
                    errorPathPrefix: static::getMountedActionDataPath($mountedActions, 'drafts'),
                );

                $currentTagIds = collect($schemaGet('tags') ?? [])
                    ->map(static fn (mixed $id): int => (int) $id)
                    ->filter(static fn (int $id): bool => $id > 0);

                $schemaSet(
                    'tags',
                    $currentTagIds
                        ->merge($result['ids'])
                        ->unique()
                        ->values()
                        ->all()
                );

                Notification::make()
                    ->title('Tags ready')
                    ->body(static::formatTaxonomyNotificationBody('tag', $result, true))
                    ->success()
                    ->send();
            });
    }

    protected static function renderTranslationFamily(?BlogPost $record): HtmlString
    {
        if (! $record) {
            return new HtmlString('');
        }

        $supportedLocales = static::localeOptions();
        $translations = $record->relationLoaded('translations')
            ? $record->getRelation('translations')
            : $record->translations()->get();

        $translationsByLocale = $translations->keyBy('locale');

        $badges = collect(array_keys($supportedLocales))
            ->map(function (string $locale) use ($translationsByLocale): string {
                /** @var BlogPost|null $translation */
                $translation = $translationsByLocale->get($locale);

                if (! $translation) {
                    return sprintf(
                        '<span class="%s">%s missing</span>',
                        'inline-flex items-center rounded-full border border-dashed border-slate-300 bg-slate-50 px-3 py-1 text-xs font-medium text-slate-500 dark:border-slate-700 dark:bg-slate-950/30 dark:text-slate-400',
                        e(strtoupper($locale)),
                    );
                }

                $status = $translation->status instanceof PostStatus
                    ? $translation->status->value
                    : (string) $translation->status;

                $classes = match ($status) {
                    PostStatus::Published->value => 'border-emerald-200 bg-emerald-50 text-emerald-700 dark:border-emerald-800/80 dark:bg-emerald-950/40 dark:text-emerald-300',
                    PostStatus::Archived->value => 'border-slate-300 bg-slate-100 text-slate-600 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300',
                    default => 'border-amber-200 bg-amber-50 text-amber-700 dark:border-amber-800/80 dark:bg-amber-950/40 dark:text-amber-300',
                };

                return sprintf(
                    '<a href="%s" class="%s">%s %s</a>',
                    e(static::getUrl('edit', ['record' => $translation])),
                    "inline-flex items-center rounded-full border px-3 py-1 text-xs font-medium transition hover:opacity-85 {$classes}",
                    e(strtoupper($locale)),
                    e(Str::headline($status)),
                );
            })
            ->implode('');

        return new HtmlString(<<<HTML
<div class="space-y-3">
    <div>
        <p class="text-sm font-medium text-slate-900 dark:text-slate-100">Translation family</p>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">Each locale is its own post record with its own slug, SEO fields, and publish state.</p>
    </div>
    <div class="flex flex-wrap gap-2">{$badges}</div>
</div>
HTML);
    }

    protected static function renderContentSourceMeta(?BlogPost $record): HtmlString
    {
        if (! $record || ! $record->isManagedByMarkdown()) {
            return new HtmlString('');
        }

        $sourcePath = e((string) $record->content_source_path);
        $lastSyncedAt = $record->content_source_synced_at?->format('M d, Y H:i');
        $syncedCopy = $lastSyncedAt ? "Last synced {$lastSyncedAt}." : 'Not synced yet.';

        return new HtmlString(<<<HTML
<div class="space-y-3">
    <div>
        <p class="text-sm font-medium text-slate-900 dark:text-slate-100">Markdown-managed post</p>
        <p class="mt-1 text-sm text-slate-600 dark:text-slate-300">This record is synced from the markdown source path <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs dark:bg-slate-900">{$sourcePath}</code>. Changes made here can be overwritten the next time <code class="rounded bg-slate-100 px-1.5 py-0.5 text-xs dark:bg-slate-900">php artisan blog:sync-content</code> runs.</p>
    </div>
    <p class="text-xs text-slate-500 dark:text-slate-400">{$syncedCopy}</p>
</div>
HTML);
    }
}
