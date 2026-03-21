<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Content\Models\BlogTag;
use App\Filament\Admin\Resources\Concerns\InteractsWithAutoSlugFields;
use App\Filament\Admin\Resources\Concerns\InteractsWithBulkTaxonomyActions;
use App\Support\Content\BlogEditorSupport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BlogTagResource extends Resource
{
    use InteractsWithAutoSlugFields;
    use InteractsWithBulkTaxonomyActions;

    protected static ?string $model = BlogTag::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Tags';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
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
                        ->unique(ignoreRecord: true),
                    sourceLabel: 'tag name',
                ),
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
            ])
            ->headerActions([
                CreateAction::make(),
                Action::make('bulkCreate')
                    ->label('Paste List')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->modalWidth('2xl')
                    ->modalHeading('Bulk create tags')
                    ->modalDescription('Paste names, review the generated slugs, then confirm.')
                    ->modalSubmitActionLabel('Create tags')
                    ->steps(static::makeBulkTaxonomyWizardSteps(
                        singularLabel: 'tag',
                        pluralLabel: 'tags',
                        modelClass: BlogTag::class,
                        nameMaxLength: 50,
                        slugMaxLength: 50,
                        placeholder: "Laravel\nBilling\nSEO",
                    ))
                    ->action(function (array $data, array $mountedActions): void {
                        $result = BlogEditorSupport::commitTaxonomyDrafts(
                            drafts: (array) ($data['drafts'] ?? []),
                            modelClass: BlogTag::class,
                            singularLabel: 'tag',
                            errorPathPrefix: static::getMountedActionDataPath($mountedActions, 'drafts'),
                        );

                        Notification::make()
                            ->title('Tags processed')
                            ->body(static::formatTaxonomyNotificationBody('tag', $result))
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
            'index' => \App\Filament\Admin\Resources\BlogTagResource\Pages\ListBlogTags::route('/'),
            'create' => \App\Filament\Admin\Resources\BlogTagResource\Pages\CreateBlogTag::route('/create'),
            'edit' => \App\Filament\Admin\Resources\BlogTagResource\Pages\EditBlogTag::route('/{record}/edit'),
        ];
    }
}
