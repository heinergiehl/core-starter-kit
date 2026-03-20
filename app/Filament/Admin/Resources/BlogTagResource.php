<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Content\Models\BlogTag;
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

class BlogTagResource extends Resource
{
    protected static ?string $model = BlogTag::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 3;

    protected static ?string $navigationLabel = 'Tags';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
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
                    ->unique(ignoreRecord: true)
                    ->helperText('Auto-generated from the tag name until you customize it.'),
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
                    ->modalHeading('Bulk create tags')
                    ->schema([
                        Textarea::make('names')
                            ->label('Tag names')
                            ->required()
                            ->rows(8)
                            ->placeholder("Laravel\nBilling\nSEO")
                            ->helperText('Paste comma-separated or one-per-line tag names. Existing slugs are reused.'),
                        Placeholder::make('bulk_create_help')
                            ->content('Example: Laravel, Billing, SEO'),
                    ])
                    ->action(function (array $data): void {
                        $result = static::createRecordsFromBulkInput($data['names'] ?? '');

                        Notification::make()
                            ->title('Tags processed')
                            ->body("Created {$result['created']} and reused {$result['existing']} existing tags.")
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

    /**
     * @return array{created:int,existing:int}
     */
    protected static function createRecordsFromBulkInput(string $input): array
    {
        $created = 0;
        $existing = 0;

        foreach (BlogEditorSupport::parseBulkNames($input) as $name) {
            $slug = BlogEditorSupport::generateSlug($name);
            $record = BlogTag::query()->firstOrCreate(
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
