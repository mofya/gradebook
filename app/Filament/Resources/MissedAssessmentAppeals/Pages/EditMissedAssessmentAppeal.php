<?php

namespace App\Filament\Resources\MissedAssessmentAppeals\Pages;

use App\Filament\Resources\MissedAssessmentAppeals\MissedAssessmentAppealResource;
use App\Models\MissedAssessmentAppeal;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMissedAssessmentAppeal extends EditRecord
{
    protected static string $resource = MissedAssessmentAppealResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    protected function afterSave(): void
    {
        /** @var MissedAssessmentAppeal $record */
        $record = $this->record;
        $record->update([
            'reviewed_by' => auth()->id(),
            'reviewed_at' => now(),
        ]);
        $record->recomputeStatus();
    }
}
