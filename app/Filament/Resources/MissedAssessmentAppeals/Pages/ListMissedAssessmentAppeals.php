<?php

namespace App\Filament\Resources\MissedAssessmentAppeals\Pages;

use App\Filament\Resources\MissedAssessmentAppeals\MissedAssessmentAppealResource;
use Filament\Resources\Pages\ListRecords;

class ListMissedAssessmentAppeals extends ListRecords
{
    protected static string $resource = MissedAssessmentAppealResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
