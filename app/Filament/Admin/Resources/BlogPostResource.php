<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Content\Models\BlogCategory;
use App\Domain\Content\Models\BlogPost;
use App\Domain\Content\Models\BlogTag;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class BlogPostResource extends Resource
{
    protected static ?string $model = BlogPost::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-document-text';

    protected static string | \UnitEnum | null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Blog Posts';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                // Main Content Column
                Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->live(onBlur: true)
                    ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state))),

                Forms\Components\TextInput::make('slug')
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true)
                    ->rules(['alpha_dash']),

                Forms\Components\Textarea::make('excerpt')
                    ->rows(2)
                    ->maxLength(500)
                    ->helperText('Brief summary shown in listing pages')
                    ->columnSpanFull(),

                // WYSIWYG Editor - Filament's built-in RichEditor using TipTap
                Forms\Components\RichEditor::make('body_html')
                    ->label('Content')
                    ->required()
                    ->fileAttachmentsDisk('public')
                    ->fileAttachmentsDirectory('blog-attachments')
                    ->fileAttachmentsVisibility('public')
                    ->resizableImages()  // Enable drag-to-resize for images
                    ->toolbarButtons([
                        'attachFiles',  // Image upload button
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

                Forms\Components\FileUpload::make('featured_image')
                    ->label('Featured Image')
                    ->image()
                    ->disk('public')
                    ->directory('blog-images')
                    ->imageResizeMode('cover')
                    ->imageCropAspectRatio('16:9')
                    ->imageResizeTargetWidth('1200')
                    ->imageResizeTargetHeight('675'),

                Forms\Components\Select::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(100),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(100),
                    ]),

                Forms\Components\Select::make('tags')
                    ->label('Tags')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->createOptionForm([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->maxLength(50)
                            ->live(onBlur: true)
                            ->afterStateUpdated(fn ($state, $set) => $set('slug', Str::slug($state))),
                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->maxLength(50)
                            ->unique('blog_tags', 'slug'),
                    ]),

                Forms\Components\Select::make('author_id')
                    ->label('Author')
                    ->relationship('author', 'name')
                    ->searchable()
                    ->preload()
                    ->default(auth()->id()),

                // Publishing Options
                Forms\Components\Toggle::make('is_published')
                    ->label('Published')
                    ->default(false),

                Forms\Components\DateTimePicker::make('published_at')
                    ->label('Publish Date')
                    ->helperText('Leave empty to publish immediately when toggled on'),

                // SEO Section
                Forms\Components\TextInput::make('meta_title')
                    ->label('SEO Title')
                    ->maxLength(60)
                    ->helperText('Recommended: 50-60 characters'),

                Forms\Components\Textarea::make('meta_description')
                    ->label('SEO Description')
                    ->rows(2)
                    ->maxLength(160)
                    ->helperText('Recommended: 150-160 characters')
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('featured_image')
                    ->label('Image')
                    ->circular()
                    ->size(40),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->limit(40)
                    ->description(fn (BlogPost $record) => Str::limit($record->excerpt, 50)),

                Tables\Columns\TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->color('primary')
                    ->sortable(),

                Tables\Columns\TextColumn::make('author.name')
                    ->label('Author')
                    ->sortable(),

                Tables\Columns\IconColumn::make('is_published')
                    ->label('Published')
                    ->boolean(),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Date')
                    ->dateTime('M d, Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('reading_time')
                    ->label('Read Time')
                    ->suffix(' min')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name'),

                Tables\Filters\TernaryFilter::make('is_published')
                    ->label('Status')
                    ->placeholder('All posts')
                    ->trueLabel('Published')
                    ->falseLabel('Draft'),
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
