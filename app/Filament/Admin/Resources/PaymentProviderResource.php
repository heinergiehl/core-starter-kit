<?php

namespace App\Filament\Admin\Resources;

use App\Domain\Billing\Models\PaymentProvider;
use App\Filament\Admin\Resources\PaymentProviderResource\Pages;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables;
use Filament\Forms;
use Filament\Actions\EditAction;
use Filament\Schemas\Components\Section;

class PaymentProviderResource extends Resource
{
    protected static ?string $model = PaymentProvider::class;

    // Use method overrides to avoid property invariance issues just in case, and match pattern
    public static function getNavigationIcon(): ?string
    {
        return 'heroicon-o-credit-card';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'Settings';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('Provider Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->required()
                            ->readOnly(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Enabled')
                            ->helperText('Enable this provider for checkouts.')
                            ->columnSpanFull(),
                    ])->columns(2),

                Section::make('Configuration')
                    ->statePath('configuration')
                    ->schema([
                        // Stripe
                        Forms\Components\TextInput::make('publishable_key')
                            ->label('Publishable Key')
                            ->helperText('Leave empty to use STRIPE_KEY from .env')
                            ->default(config('services.stripe.key'))
                            ->formatStateUsing(fn ($state) => $state ?? config('services.stripe.key'))
                            ->visible(fn ($get) => $get('../slug') === 'stripe'),
                        Forms\Components\TextInput::make('secret_key')
                            ->label('Secret Key')
                            ->password()
                            ->revealable()
                            ->helperText('Leave empty to use STRIPE_SECRET from .env')
                            ->default(config('services.stripe.secret'))
                            ->formatStateUsing(fn ($state) => $state ?? config('services.stripe.secret'))
                            ->visible(fn ($get) => $get('../slug') === 'stripe'),
                        Forms\Components\TextInput::make('webhook_secret')
                            ->label('Webhook Secret')
                            ->password()
                            ->revealable()
                            ->helperText('Leave empty to use STRIPE_WEBHOOK_SECRET from .env')
                            ->default(config('services.stripe.webhook_secret'))
                            ->formatStateUsing(fn ($state) => $state ?? config('services.stripe.webhook_secret'))
                            ->visible(fn ($get) => $get('../slug') === 'stripe'),

                        // Paddle
                        Forms\Components\Select::make('environment')
                            ->options([
                                'production' => 'Production',
                                'sandbox' => 'Sandbox',
                            ])
                            ->default(config('services.paddle.environment', 'production'))
                            ->formatStateUsing(fn ($state) => $state ?? config('services.paddle.environment', 'production'))
                            ->visible(fn ($get) => $get('../slug') === 'paddle'),
                        Forms\Components\TextInput::make('vendor_id')
                            ->label('Vendor ID')
                            ->helperText('Leave empty to use PADDLE_VENDOR_ID from .env')
                            ->default(config('services.paddle.vendor_id'))
                            ->formatStateUsing(fn ($state) => $state ?? config('services.paddle.vendor_id'))
                            ->visible(fn ($get) => $get('../slug') === 'paddle'),
                        Forms\Components\TextInput::make('api_key')
                            ->label('API Key (Auth Code)')
                            ->password()
                            ->revealable()
                            ->helperText('Leave empty to use PADDLE_API_KEY from .env')
                            ->default(config('services.paddle.api_key'))
                            ->formatStateUsing(fn ($state) => $state ?? config('services.paddle.api_key'))
                            ->visible(fn ($get) => $get('../slug') === 'paddle'),
                        Forms\Components\TextInput::make('webhook_secret')
                            ->label('Webhook Secret')
                            ->password()
                            ->revealable()
                            ->helperText('Leave empty to use PADDLE_WEBHOOK_SECRET from .env')
                            ->default(config('services.paddle.webhook_secret'))
                            ->formatStateUsing(fn ($state) => $state ?? config('services.paddle.webhook_secret'))
                            ->visible(fn ($get) => $get('../slug') === 'paddle'),
                    ])->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->sortable()
                    ->searchable(),
                Tables\Columns\TextColumn::make('slug')
                    ->badge(),
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                //
            ])
            ->paginated(false);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPaymentProviders::route('/'),
            'edit' => Pages\EditPaymentProvider::route('/{record}/edit'),
        ];
    }
}
