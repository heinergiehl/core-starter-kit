<?php

namespace App\Filament\Admin\Resources\PriceResource\Pages;

use App\Filament\Admin\Resources\PriceResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;

class CreatePrice extends CreateRecord
{
    protected static string $resource = PriceResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResourceUrl('index');
    }

    protected function getCreatedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Price created')
            ->body('Add another price or review the list.');
    }
}
