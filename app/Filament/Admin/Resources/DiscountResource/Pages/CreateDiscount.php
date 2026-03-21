<?php

namespace App\Filament\Admin\Resources\DiscountResource\Pages;

use App\Domain\Billing\Services\BillingProviderManager;
use App\Filament\Admin\Resources\DiscountResource;
use Filament\Resources\Pages\CreateRecord;

class CreateDiscount extends CreateRecord
{
    protected static string $resource = DiscountResource::class;

    protected function handleRecordCreation(array $data): \Illuminate\Database\Eloquent\Model
    {
        $provider = (string) ($data['provider'] ?? 'provider');

        return \Illuminate\Support\Facades\DB::transaction(function () use ($data, $provider) {
            $discount = static::getModel()::create($data);

            try {
                $adapter = app(BillingProviderManager::class)->adapter($provider);

                $providerId = $adapter->createDiscount($discount);
                $discount->update(['provider_id' => $providerId]);
            } catch (\Throwable $exception) {
                \Filament\Notifications\Notification::make()
                    ->title('Failed to sync discount to '.ucfirst($provider))
                    ->body($exception->getMessage())
                    ->danger()
                    ->persistent()
                    ->send();

                throw $exception;
            }

            return $discount;
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
