<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Content\Models\BlogCategory;
use App\Support\Content\BlogEditorSupport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class BlogCategoryResource extends Resource
{
    protected static ?string $model = BlogCategory::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-folder';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Categories';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
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
                    ->unique(ignoreRecord: true)
                    ->helperText('Auto-generated from the category name until you customize it.'),

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
                    ->modalHeading('Bulk create categories')
                    ->schema([
                        Textarea::make('names')
                            ->label('Category names')
                            ->required()
                            ->rows(8)
                            ->placeholder("Billing\nProduct Updates\nGrowth")
                            ->helperText('Paste comma-separated or one-per-line category names. Existing slugs are reused.'),
                        Placeholder::make('bulk_create_help')
                            ->content('Example: Billing, Product Updates, Growth'),
                    ])
                    ->action(function (array $data): void {
                        $result = static::createRecordsFromBulkInput($data['names'] ?? '');

                        Notification::make()
                            ->title('Categories processed')
                            ->body("Created {$result['created']} and reused {$result['existing']} existing categories.")
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

    /**
     * @return array{created:int,existing:int}
     */
    protected static function createRecordsFromBulkInput(string $input): array
    {
        $created = 0;
        $existing = 0;

        foreach (BlogEditorSupport::parseBulkNames($input) as $name) {
            $slug = BlogEditorSupport::generateSlug($name);
            $record = BlogCategory::query()->firstOrCreate(
                ['slug' => $slug],
                ['name' => $name]
            );

            $record->wasRecentlyCreated ? $created++ : $existing++;
        }

        return [
            'created' => $created,
            'existing' => $existing,
        ];
    }
}
