<?php

namespace App\Filament\Admin\Resources\SurveyResponses\Pages;

use App\Filament\Admin\Resources\SurveyResponses\SurveyResponseResource;
use Filament\Resources\Pages\ViewRecord;

class ViewSurveyResponse extends ViewRecord
{
    protected static string $resource = SurveyResponseResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
