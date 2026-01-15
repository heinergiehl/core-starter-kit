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
use Filament\Forms;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class FeatureRequestResource extends Resource
{
    protected static ?string $model = FeatureRequest::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-light-bulb';

    protected static string | \UnitEnum | null $navigationGroup = 'Product Management';

    protected static ?string $navigationLabel = 'Roadmap';

    protected static ?int $navigationSort = 6;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Request')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(120),
                        Forms\Components\TextInput::make('slug')
                            ->disabled(),
                        Forms\Components\Textarea::make('description')
                            ->rows(4),
                        Forms\Components\TextInput::make('category')
                            ->maxLength(80),
                        Forms\Components\Select::make('status')
                            ->options([
                                'planned' => 'Planned',
                                'in_progress' => 'In progress',
                                'complete' => 'Complete',
                            ])
                            ->required(),
                        Forms\Components\Toggle::make('is_public')
                            ->label('Public'),
                        Forms\Components\DateTimePicker::make('released_at')
                            ->label('Released at'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('category')
                    ->toggleable(),
                Tables\Columns\TextColumn::make('votes_count')
                    ->label('Votes')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_public')
                    ->label('Public')
                    ->boolean(),
                Tables\Columns\TextColumn::make('created_at')
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
