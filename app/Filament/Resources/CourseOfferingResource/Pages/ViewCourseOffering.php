<?php

namespace App\Filament\Resources\CourseOfferingResource\Pages;

use App\Filament\Resources\CourseOfferingResource;
use Filament\Actions;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewCourseOffering extends ViewRecord
{
    protected static string $resource = CourseOfferingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('manage_links')
                ->label('Manage Links')
                ->color('success')
                ->icon('heroicon-o-link')
                ->url(fn () => CourseOfferingResource::getUrl('manage-links', ['record' => $this->record])),

            EditAction::make(),
        ];
    }
}
