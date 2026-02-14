<?php

namespace App\Filament\Resources\AssessmentGroupResource\Pages;

use App\Filament\Resources\AssessmentGroupResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditAssessmentGroup extends EditRecord
{
    protected static string $resource = AssessmentGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make(),
        ];
    }
}
