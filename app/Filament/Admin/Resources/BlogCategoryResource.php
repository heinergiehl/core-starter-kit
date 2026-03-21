<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Content\Models\BlogCategory;
use App\Filament\Admin\Resources\Concerns\InteractsWithAutoSlugFields;
use App\Filament\Admin\Resources\Concerns\InteractsWithBulkTaxonomyActions;
use App\Support\Content\BlogEditorSupport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BlogCategoryResource extends Resource
{
    use InteractsWithAutoSlugFields;
    use InteractsWithBulkTaxonomyActions;

    protected static ?string $model = BlogCategory::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Categories';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
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
                        ->unique(ignoreRecord: true),
                    sourceLabel: 'category name',
                ),

                Textarea::make('description')
                    ->rows(2)
                    ->maxLength(500)
                    ->columnSpanFull(),
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

                TextColumn::make('slug')
                    ->searchable(),

                TextColumn::make('posts_count')
                    ->counts('posts')
                    ->label('Posts')
                    ->badge()
                    ->color('primary'),

                TextColumn::make('created_at')
                    ->dateTime('M d, Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                CreateAction::make(),
                Action::make('bulkCreate')
                    ->label('Paste List')
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
                    ->action(function (array $data, array $mountedActions): void {
                        $result = BlogEditorSupport::commitTaxonomyDrafts(
                            drafts: (array) ($data['drafts'] ?? []),
                            modelClass: BlogCategory::class,
                            singularLabel: 'category',
                            errorPathPrefix: static::getMountedActionDataPath($mountedActions, 'drafts'),
                        );

                        Notification::make()
                            ->title('Categories processed')
                            ->body(static::formatTaxonomyNotificationBody('category', $result))
                            ->success()
                            ->send();
                    }),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Admin\Resources\BlogCategoryResource\Pages\ListBlogCategories::route('/'),
            'create' => \App\Filament\Admin\Resources\BlogCategoryResource\Pages\CreateBlogCategory::route('/create'),
            'edit' => \App\Filament\Admin\Resources\BlogCategoryResource\Pages\EditBlogCategory::route('/{record}/edit'),
        ];
    }
}
