<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Billing\Models\Product;
use App\Filament\Admin\Resources\ProductResource\Pages\CreateProduct;
use App\Filament\Admin\Resources\ProductResource\Pages\EditProduct;
use App\Filament\Admin\Resources\ProductResource\Pages\ListProducts;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Artisan;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-cube';

    protected static string | \UnitEnum | null $navigationGroup = 'Product Management';

    protected static ?string $navigationLabel = 'Products';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Forms\Components\TextInput::make('key')
                    ->label('Key')
                    ->required()
                    ->maxLength(64)
                    ->unique(ignoreRecord: true),
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('summary')
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->rows(4)
                    ->columnSpanFull(),
                Forms\Components\Select::make('type')
                    ->options([
                        'subscription' => 'Subscription',
                        'one_time' => 'One-time',
                    ])
                    ->required()
                    ->default('subscription'),
                Forms\Components\Toggle::make('seat_based')
                    ->label('Seat-based'),
                Forms\Components\TextInput::make('max_seats')
                    ->label('Max seats')
                    ->numeric()
                    ->minValue(0),
                Forms\Components\Toggle::make('is_featured')
                    ->label('Featured'),
                Forms\Components\Toggle::make('is_active')
                    ->label('Active')
                    ->default(true),
                Forms\Components\Textarea::make('features')
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
                        if (!$state) {
                            return [];
                        }
                        $lines = preg_split('/\r?\n/', (string) $state);
                        return array_values(array_filter(array_map('trim', $lines)));
                    }),
                Forms\Components\Textarea::make('entitlements')
                    ->label('Entitlements (JSON)')
                    ->rows(6)
                    ->columnSpanFull()
                    ->helperText('Example: {"storage_limit_mb": 2048, "support_sla": "priority"}')
                    ->formatStateUsing(function ($state): ?string {
                        if (!$state) {
                            return null;
                        }
                        return json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                    })
                    ->dehydrateStateUsing(function ($state): ?array {
                        if (!$state) {
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
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('key')
                    ->badge()
                    ->sortable(),
                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => $state === 'one_time' ? 'warning' : 'primary'),
                Tables\Columns\TextColumn::make('provider')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'stripe' => 'primary',
                        'lemonsqueezy' => 'warning',
                        'paddle' => 'success',
                        default => 'gray',
                    })
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Active' : 'Inactive')
                    ->color(fn (bool $state): string => $state ? 'success' : 'gray'),
            ])
            ->headerActions([
                CreateAction::make(),
                Action::make('sync')
                    ->label('Import from Providers')
                    ->icon('heroicon-o-arrow-path')
                    ->color('gray')
                    ->action(function () {
                        Artisan::call('billing:sync-products');
                        Notification::make()
                            ->title('Sync complete')
                            ->body('Products and prices have been synced from all providers.')
                            ->success()
                            ->send();
                    }),
                Action::make('publish')
                    ->label('Publish to Provider')
                    ->icon('heroicon-o-cloud-arrow-up')
                    ->color('primary')
                    ->form([
                        Forms\Components\Select::make('provider')
                            ->options(self::providerOptions())
                            ->required(),
                        Forms\Components\Toggle::make('update')
                            ->label('Update existing')
                            ->default(false),
                    ])
                    ->action(function (array $data) {
                        $provider = (string) ($data['provider'] ?? '');
                        if ($provider === '') {
                            return;
                        }

                        Artisan::call('billing:publish-catalog', [
                            'provider' => $provider,
                            '--apply' => true,
                            '--update' => (bool) ($data['update'] ?? false),
                        ]);

                        Notification::make()
                            ->title('Publish complete')
                            ->body("Published catalog to {$provider}.")
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('name')
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
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
