<?php

namespace App\Filament\Admin\Resources\PaymentProviderResource\Pages;

use App\Domain\Billing\Services\PaymentProviderSafetyService;
use App\Filament\Admin\Resources\PaymentProviderResource;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use RuntimeException;

class ListPaymentProviders extends ListRecords
{
    protected static string $resource = PaymentProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('addSupportedProvider')
                ->label('Add Supported Provider')
                ->icon('heroicon-o-plus')
                ->color('gray')
                ->modalDescription('Supported providers are defined in App\\Enums\\BillingProvider and implemented in App\\Domain\\Billing\\Services\\BillingProviderManager.')
                ->disabled(fn (): bool => empty(app(PaymentProviderSafetyService::class)->missingSupportedProviderOptions()))
                ->tooltip(fn (): ?string => empty(app(PaymentProviderSafetyService::class)->missingSupportedProviderOptions())
                    ? 'All supported providers are already configured.'
                    : null)
                ->form([
                    Select::make('slug')
                        ->label('Provider')
                        ->options(fn (): array => app(PaymentProviderSafetyService::class)->missingSupportedProviderOptions())
                        ->required(),
                ])
                ->action(function (array $data): void {
                    try {
                        $provider = app(PaymentProviderSafetyService::class)->addSupportedProvider((string) ($data['slug'] ?? ''));

                        Notification::make()
                            ->title('Provider added')
                            ->body("{$provider->name} has been added. Configure credentials and enable it when ready.")
                            ->success()
                            ->send();
                    } catch (RuntimeException $exception) {
                        Notification::make()
                            ->title('Could not add provider')
                            ->body($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }
}
