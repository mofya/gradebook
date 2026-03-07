<?php

namespace App\Filament\Resources\CourseResource\Pages;

use App\Filament\Resources\CourseResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditCourse extends EditRecord
{
    protected static string $resource = CourseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('assessment_weights')
                ->label('Assessment Weights')
                ->color('warning')
                ->icon('heroicon-o-scale')
                ->url(fn () => CourseResource::getUrl('assessment-weights', ['record' => $this->record])),

            Actions\DeleteAction::make()
                ->modalHeading('Delete Course')
                ->modalDescription('Are you sure? This will remove the course and all associated offerings.'),
        ];
    }
}
