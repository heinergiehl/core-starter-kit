<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Billing\Models\WebhookEvent;
use App\Filament\Admin\Resources\WebhookEventResource\Pages\ListWebhookEvents;
use App\Filament\Admin\Resources\WebhookEventResource\Pages\ViewWebhookEvent;
use App\Jobs\ProcessWebhookEvent;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class WebhookEventResource extends Resource
{
    protected static ?string $model = WebhookEvent::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-bolt';

    protected static string | \UnitEnum | null $navigationGroup = 'Billing';

    protected static ?string $recordTitleAttribute = 'event_id';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->schema([
            Section::make('Event')
                ->schema([
                    TextEntry::make('provider')->badge(),
                    TextEntry::make('event_id')->label('Event ID'),
                    TextEntry::make('type'),
                    TextEntry::make('status')->badge(),
                    TextEntry::make('received_at')->dateTime(),
                    TextEntry::make('processed_at')->dateTime(),
                    TextEntry::make('error_message')->label('Error'),
                ])
                ->columns(2),
            Section::make('Payload')
                ->schema([
                    TextEntry::make('payload')
                        ->formatStateUsing(fn ($state): string => json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES))
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('provider')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('event_id')
                    ->copyable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('type')
                    ->limit(30)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'processed' => 'success',
                        'failed' => 'danger',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('received_at')
                    ->label('Received')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('processed_at')
                    ->label('Processed')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->defaultSort('received_at', 'desc')
            ->actions([
                ViewAction::make(),
                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->visible(fn (WebhookEvent $record): bool => $record->status === 'failed')
                    ->requiresConfirmation()
                    ->action(function (WebhookEvent $record): void {
                        $record->update([
                            'status' => 'received',
                            'error_message' => null,
                        ]);

                        ProcessWebhookEvent::dispatch($record->id);
                    }),
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
            'index' => ListWebhookEvents::route('/'),
            'view' => ViewWebhookEvent::route('/{record}'),
        ];
    }
}
