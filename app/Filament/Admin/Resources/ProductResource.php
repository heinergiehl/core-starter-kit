<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Billing\Models\Product;
use App\Filament\Admin\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Admin\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Admin\Resources\ProductResource\Pages\ListProducts;
use App\Jobs\SyncProductsJob;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cube';

    protected static string|\UnitEnum|null $navigationGroup = 'Product Management';

    protected static ?string $navigationLabel = 'Products';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('key')
                    ->label('Key')
                    ->required()
                    ->maxLength(64)
                    ->readOnly()
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->readOnly()
                    ->helperText('Managed by Provider (Sync to update)'),
                TextInput::make('summary')
                    ->maxLength(255)
                    ->helperText('Local marketing summary (not synced)'),
                Textarea::make('description')
                    ->rows(4)
                    ->columnSpanFull()
                    ->readOnly()
                    ->helperText('Managed by Provider (Sync to update)'),
                Select::make('type')
                    ->options([
                        'subscription' => 'Subscription',
                        'one_time' => 'One-time',
                    ])
                    ->required()
                    ->default('subscription')
                    ->disabled(),
                Toggle::make('is_featured')
                    ->label('Featured'),
                Toggle::make('is_active')
                    ->label('Active')
                    ->default(false),
                Textarea::make('features')
                    ->label('Features (one per line)')
                    ->rows(5)
                    ->columnSpanFull()
                    ->formatStateUsing(function ($state): string {
                        if (is_array($state)) {
                            return implode("\n", $state);
                        }

                        return (string) $state;
                    })
                    ->dehydrateStateUsing(function ($state): array {
                        if (! $state) {
                            return [];
                        }
                        $lines = preg_split('/\r?\n/', (string) $state);

                        return array_values(array_filter(array_map('trim', $lines)));
                    }),
                Textarea::make('entitlements')
                    ->label('Entitlements (JSON)')
                    ->rows(6)
                    ->columnSpanFull()
                    ->helperText('Example: {"storage_limit_mb": 2048, "support_sla": "priority"}')
                    ->formatStateUsing(function ($state): ?string {
                        if (! $state) {
                            return null;
                        }

                        return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    })
                    ->dehydrateStateUsing(function ($state): ?array {
                        if (! $state) {
                            return null;
                        }
                        $decoded = json_decode((string) $state, true);

                        return is_array($decoded) ? $decoded : null;
                    })
                    ->rules(['nullable', 'json']),
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
                TextColumn::make('key')
                    ->badge()
                    ->sortable(),
                TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'one_time' ? 'warning' : 'primary'),
                TextColumn::make('providerMappings.provider')
                    ->label('Providers')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'stripe' => 'primary',
                        'lemonsqueezy' => 'warning',
                        'paddle' => 'success',
                        'default' => 'gray', // Added default case to match style
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => ucfirst($state))
                    ->toggleable(),
                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->default(true) // Only show active by default
                    ->trueLabel('Active Only')
                    ->falseLabel('Inactive Only')
                    ->placeholder('All Products'),
            ])
            ->headerActions([
                // CreateAction::make(), // Disabled for Provider-First
                Action::make('sync')
                    ->label('Import from Providers')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->disabled(fn () => false) // Placeholder if we had a job status to check
                    ->form([
                        Toggle::make('include_deleted')
                            ->label('Include deleted (override local deletions)')
                            ->default(false),
                    ])
                    ->disabled(fn () => \Illuminate\Support\Facades\Cache::has('sync_products_job'))
                    ->tooltip(fn () => \Illuminate\Support\Facades\Cache::has('sync_products_job') ? 'Import is currently running in the background.' : 'Sync products from payment providers')
                    ->action(function (array $data) {
                        if (Cache::has('sync_products_job')) {
                            Notification::make()
                                ->title('Sync already in progress')
                                ->body('Please wait for the current synchronization to finish.')
                                ->warning()
                                ->send();

                            return;
                        }

                        // Lock the process for 10 minutes to prevent overlapping runs
                        Cache::put('sync_products_job', true, 600);

                        $includeDeleted = (bool) ($data['include_deleted'] ?? false);
                        
                        SyncProductsJob::dispatch($includeDeleted, auth()->id());

                        Notification::make()
                            ->title('Sync started')
                            ->body('Product synchronization has started in the background. You will be notified when it completes.')
                            ->info()
                            ->send();
                    }),

                // Publish Action Removed for Provider-First
            ])
            ->defaultSort('name')
            ->actions([
                EditAction::make(),
                // Delete removed: Products are managed on provider side (Paddle/Stripe/LemonSqueezy)
            ])
            ->bulkActions([
                // Bulk delete removed: Products are managed on provider side
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            // 'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }

    private static function providerOptions(): array
    {
        $options = [];
        $providers = config('saas.billing.providers', ['stripe', 'paddle', 'lemonsqueezy']);

        foreach ($providers as $provider) {
            $options[$provider] = ucfirst((string) $provider);
        }

        return $options;
    }
}
