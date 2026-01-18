<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Billing\Models\ProviderDeletionOutbox;
use App\Filament\Admin\Resources\ProviderDeletionOutboxResource\Pages\ListProviderDeletionOutboxes;
use App\Jobs\ProcessProviderDeletionJob;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class ProviderDeletionOutboxResource extends Resource
{
    protected static ?string $model = ProviderDeletionOutbox::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-archive-box';

    protected static string | \UnitEnum | null $navigationGroup = 'Billing';

    protected static ?string $navigationLabel = 'Provider Deletions';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'provider_id';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('provider')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'stripe' => 'primary',
                        'paddle' => 'success',
                        'lemonsqueezy' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('entity_type')
                    ->label('Entity')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('provider_id')
                    ->label('Provider ID')
                    ->copyable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        ProviderDeletionOutbox::STATUS_COMPLETED => 'success',
                        ProviderDeletionOutbox::STATUS_FAILED => 'danger',
                        ProviderDeletionOutbox::STATUS_PROCESSING => 'info',
                        default => 'warning',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('attempts')
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_error')
                    ->label('Last error')
                    ->limit(40)
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('Completed')
                    ->dateTime()
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('retry')
                    ->label('Retry')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->visible(fn (ProviderDeletionOutbox $record): bool => $record->status === ProviderDeletionOutbox::STATUS_FAILED)
                    ->requiresConfirmation()
                    ->action(function (ProviderDeletionOutbox $record): void {
                        $record->update([
                            'status' => ProviderDeletionOutbox::STATUS_PENDING,
                            'last_error' => null,
                            'completed_at' => null,
                            'attempts' => 0,
                        ]);

                        ProcessProviderDeletionJob::dispatch($record->id);

                        Notification::make()
                            ->title('Deletion retry queued')
                            ->body("Provider deletion for {$record->provider} {$record->entity_type} queued.")
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    BulkAction::make('retrySelected')
                        ->label('Retry selected')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->requiresConfirmation()
                        ->action(function ($records): void {
                            $count = 0;

                            foreach ($records as $record) {
                                if ($record->status === ProviderDeletionOutbox::STATUS_COMPLETED) {
                                    continue;
                                }

                                $record->update([
                                    'status' => ProviderDeletionOutbox::STATUS_PENDING,
                                    'last_error' => null,
                                    'completed_at' => null,
                                    'attempts' => 0,
                                ]);

                                ProcessProviderDeletionJob::dispatch($record->id);
                                $count++;
                            }

                            if ($count === 0) {
                                Notification::make()
                                    ->title('Nothing to retry')
                                    ->body('All selected deletions are already completed.')
                                    ->warning()
                                    ->send();
                                return;
                            }

                            Notification::make()
                                ->title('Deletion retries queued')
                                ->body("Queued {$count} provider deletions for retry.")
                                ->success()
                                ->send();
                        }),
                ]),
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
            'index' => ListProviderDeletionOutboxes::route('/'),
        ];
    }
}
