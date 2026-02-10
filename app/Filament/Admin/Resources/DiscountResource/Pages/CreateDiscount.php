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
        return \Illuminate\Support\Facades\DB::transaction(function () use ($data) {
            $discount = static::getModel()::create($data);

            try {
                $provider = $data['provider'];
                $adapter = app(BillingProviderManager::class)->adapter((string) $provider);

                $providerId = $adapter->createDiscount($discount);
                $discount->update(['provider_id' => $providerId]);
            } catch (\Exception $e) {
                // We rely on the transaction rollback to undo the local creation
                // if we re-throw.

                \Filament\Notifications\Notification::make()
                    ->title('Failed to sync discount to '.ucfirst($provider))
                    ->body($e->getMessage())
                    ->danger()
                    ->persistent()
                    ->send();

                throw $e;
            }

            return $discount;
        });
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
