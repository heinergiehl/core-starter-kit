<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Billing\Models\Subscription;
use App\Filament\Admin\Resources\SubscriptionResource\Pages\ListSubscriptions;
use App\Filament\Admin\Resources\SubscriptionResource\Pages\ViewSubscription;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class SubscriptionResource extends Resource
{
    protected static ?string $model = Subscription::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static string | \UnitEnum | null $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Subscriptions';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'provider_id';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Subscription')
                ->schema([
                    TextEntry::make('team.name')->label('Team'),
                    TextEntry::make('provider')->badge(),
                    TextEntry::make('provider_id')->label('Provider ID'),
                    TextEntry::make('plan_key')->label('Plan'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('quantity'),
                    TextEntry::make('trial_ends_at')->dateTime(),
                    TextEntry::make('renews_at')->dateTime(),
                    TextEntry::make('ends_at')->dateTime(),
                    TextEntry::make('canceled_at')->dateTime(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('team.name')
                    ->label('Team')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('provider')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('plan_key')
                    ->label('Plan')
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'active', 'trialing' => 'success',
                        'canceled', 'cancelled' => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('quantity')
                    ->sortable(),
                Tables\Columns\TextColumn::make('renews_at')
                    ->label('Renews')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
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
            'index' => ListSubscriptions::route('/'),
            'view' => ViewSubscription::route('/{record}'),
        ];
    }
}
