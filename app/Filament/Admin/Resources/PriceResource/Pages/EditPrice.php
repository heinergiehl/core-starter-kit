<?php

namespace App\Filament\Admin\Resources\PriceResource\Pages;

use App\Filament\Admin\Resources\PriceResource;
use Filament\Resources\Pages\EditRecord;

class EditPrice extends EditRecord
{
    protected static string $resource = PriceResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return PriceResource::normalizePriceDataForPersistence(
            $data,
            data_get($this->data, 'pricing_mode')
        );
    }
}
