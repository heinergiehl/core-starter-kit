<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Feedback\Models\FeatureRequest;
use App\Filament\Admin\Resources\FeatureRequestResource\Pages\EditFeatureRequest;
use App\Filament\Admin\Resources\FeatureRequestResource\Pages\ListFeatureRequests;
use App\Filament\Admin\Resources\FeatureRequestResource\Pages\ViewFeatureRequest;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class FeatureRequestResource extends Resource
{
    protected static ?string $model = FeatureRequest::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-light-bulb';

    protected static string|\UnitEnum|null $navigationGroup = 'Product Management';

    protected static ?string $navigationLabel = 'Roadmap';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Request')
                    ->schema([
                        TextInput::make('title')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('slug')
                            ->disabled(),
                        Textarea::make('description')
                            ->rows(4),
                        Select::make('category')
                            ->options(\App\Enums\FeatureCategory::class)
                            ->searchable()
                            ->required(),
                        Select::make('status')
                            ->options(\App\Enums\FeatureStatus::class)
                            ->required(),
                        Toggle::make('is_public')
                            ->label('Public'),
                        DateTimePicker::make('released_at')
                            ->label('Released at'),
                    ])
                    ->columns(2),
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
                TextColumn::make('category')
                    ->toggleable(),
                TextColumn::make('votes_count')
                    ->label('Votes')
                    ->sortable(),
                IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('votes_count', 'desc')
            ->actions([
                ViewAction::make(),
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeatureRequests::route('/'),
            'view' => ViewFeatureRequest::route('/{record}'),
            'edit' => EditFeatureRequest::route('/{record}/edit'),
        ];
    }
}
