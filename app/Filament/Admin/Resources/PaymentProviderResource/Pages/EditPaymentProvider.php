<?php

namespace App\Filament\Admin\Resources\PaymentProviderResource\Pages;

use App\Domain\Billing\Services\PaymentProviderSafetyService;
use App\Filament\Admin\Resources\PaymentProviderResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;

class EditPaymentProvider extends EditRecord
{
    protected static string $resource = PaymentProviderResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $isActive = (bool) ($data['is_active'] ?? false);

        if (! $isActive) {
            $reason = app(PaymentProviderSafetyService::class)->disableGuardReason($this->getRecord());

            if ($reason !== null) {
                Notification::make()
                    ->title('Provider cannot be disabled')
                    ->body($reason)
                    ->danger()
                    ->send();

                throw new Halt;
            }
        }

        return $data;
    }
}
