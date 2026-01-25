<?php

namespace App\Filament\Admin\Resources\FeatureRequestResource\Pages;

use App\Filament\Admin\Resources\FeatureRequestResource;
use Filament\Resources\Pages\EditRecord;

class EditFeatureRequest extends EditRecord
{
    protected static string $resource = FeatureRequestResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('edit', ['record' => $this->record]);
    }
}
