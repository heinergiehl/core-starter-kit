<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Content\Models\BlogPost;
use App\Support\Content\BlogEditorSupport;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class BlogPostResource extends Resource
{
    protected static ?string $model = BlogPost::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Blog Posts';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Content')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Stripe vs Paddle for SaaS billing')
                            ->helperText('This is the public headline shown on the post page and in cards.')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, ?string $old, Get $get, Set $set): void {
                                if (! BlogEditorSupport::shouldAutoUpdateSlug($get('slug'), $old)) {
                                    return;
                                }

                                $set('slug', BlogEditorSupport::generateSlug($state));
                            }),

                        TextInput::make('slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->rules(['alpha_dash'])
                            ->placeholder('stripe-vs-paddle-for-saas-billing')
                            ->helperText('Auto-generated from the title until you customize it.'),

                        Textarea::make('excerpt')
                            ->rows(3)
                            ->maxLength(500)
                            ->placeholder('Short summary used on cards, feeds, and as the default SEO description.')
                            ->helperText('Recommended for blog cards, feeds, and meta description fallback.')
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

                Section::make('Metadata')
                    ->schema([
                        FileUpload::make('featured_image')
                            ->label('Featured Image')
                            ->image()
                            ->helperText('Used in cards and as the visual fallback for sharing.')
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
                            ->helperText('Defaults to the signed-in admin.'),

                        Select::make('category_id')
                            ->label('Primary Category')
                            ->relationship('category', 'name')
                            ->searchable()
                            ->preload()
                            ->helperText('Use one primary category per post. Need many categories? Use Categories > Paste List.')
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(100)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (?string $state, ?string $old, Get $get, Set $set): void {
                                        if (! BlogEditorSupport::shouldAutoUpdateSlug($get('slug'), $old)) {
                                            return;
                                        }

                                        $set('slug', BlogEditorSupport::generateSlug($state));
                                    }),
                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(100)
                                    ->helperText('Auto-generated from the category name until you customize it.'),
                            ]),

                        Select::make('tags')
                            ->label('Tags')
                            ->relationship('tags', 'name')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->helperText('Use tags for secondary topics. Need many new tags? Use Tags > Paste List.')
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->required()
                                    ->maxLength(50)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (?string $state, ?string $old, Get $get, Set $set): void {
                                        if (! BlogEditorSupport::shouldAutoUpdateSlug($get('slug'), $old)) {
                                            return;
                                        }

                                        $set('slug', BlogEditorSupport::generateSlug($state));
                                    }),
                                TextInput::make('slug')
                                    ->required()
                                    ->maxLength(50)
                                    ->unique('blog_tags', 'slug')
                                    ->helperText('Auto-generated from the tag name until you customize it.'),
                            ]),

                        ToggleButtons::make('status')
                            ->label('Status')
                            ->inline()
                            ->options(\App\Enums\PostStatus::class)
                            ->default(\App\Enums\PostStatus::Draft)
                            ->required(),

                        DateTimePicker::make('published_at')
                            ->label('Publish Date')
                            ->helperText('Leave empty to publish immediately when status is Published.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Search Appearance')
                    ->description('Optional SEO overrides. Leave these blank to use the title and excerpt automatically.')
                    ->schema([
                        TextInput::make('meta_title')
                            ->label('SEO Title')
                            ->maxLength(60)
                            ->placeholder(fn (Get $get): string => (string) ($get('title') ?: 'Uses the post title'))
                            ->helperText('Optional. Recommended: 50-60 characters.'),

                        Textarea::make('meta_description')
                            ->label('SEO Description')
                            ->rows(3)
                            ->maxLength(160)
                            ->placeholder(fn (Get $get): string => (string) ($get('excerpt') ?: 'Uses the excerpt or body fallback'))
                            ->helperText('Optional. Recommended: 150-160 characters.')
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
            ->columns([
                ImageColumn::make('featured_image')
                    ->label('Image')
                    ->circular()
                    ->size(40),

                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->description(fn (BlogPost $record) => str($record->excerpt)->limit(50)),

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
}
