<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Billing\Models\BillingCustomer;
use App\Filament\Admin\Resources\BillingCustomerResource\Pages\ListBillingCustomers;
use App\Filament\Admin\Resources\BillingCustomerResource\Pages\ViewBillingCustomer;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class BillingCustomerResource extends Resource
{
    protected static ?string $model = BillingCustomer::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Customers';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'email';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Customer')
                ->schema([
                    TextEntry::make('user.name')->label('Customer'),
                    TextEntry::make('provider')->badge(),
                    TextEntry::make('provider_id')->label('Provider ID'),
                    TextEntry::make('email'),
                    TextEntry::make('created_at')->dateTime(),
                    TextEntry::make('updated_at')->dateTime(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Customer')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('provider')
                    ->badge()
                    ->sortable(),
                TextColumn::make('provider_id')
                    ->label('Provider ID')
                    ->toggleable(),
                TextColumn::make('email')
                    ->searchable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                ViewAction::make(),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBillingCustomers::route('/'),
            'view' => ViewBillingCustomer::route('/{record}'),
        ];
    }
}
