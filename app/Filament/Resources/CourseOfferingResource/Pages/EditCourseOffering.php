<?php

namespace App\Filament\Resources\CourseOfferingResource\Pages;

use App\Enums\OfferingStatus;
use App\Exports\GradeSheetExport;
use App\Exports\UNZAMarkSheetExport;
use App\Filament\Resources\CourseOfferingResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Maatwebsite\Excel\Facades\Excel;

class EditCourseOffering extends EditRecord
{
    protected static string $resource = CourseOfferingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('activate')
                ->label('Activate')
                ->color('success')
                ->icon('heroicon-o-play')
                ->requiresConfirmation()
                ->modalHeading('Activate Course Offering')
                ->modalDescription('This will change the offering status from Draft to Active, allowing grade entry.')
                ->visible(fn () => $this->record->status === OfferingStatus::Draft)
                ->action(function () {
                    try {
                        $this->record->activate();
                        Notification::make()->title('Offering activated.')->success()->send();
                    } catch (\LogicException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),

            Actions\Action::make('lock')
                ->label('Lock')
                ->color('warning')
                ->icon('heroicon-o-lock-closed')
                ->requiresConfirmation()
                ->modalHeading('Lock Course Offering')
                ->modalDescription('This will lock the offering, preventing further grade modifications.')
                ->visible(fn () => $this->record->status === OfferingStatus::Active)
                ->action(function () {
                    try {
                        $this->record->lock();
                        Notification::make()->title('Offering locked.')->success()->send();
                    } catch (\LogicException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),

            Actions\Action::make('publish')
                ->label('Publish')
                ->color('primary')
                ->icon('heroicon-o-eye')
                ->requiresConfirmation()
                ->modalHeading('Publish Course Offering')
                ->modalDescription('This will publish the offering, making grades visible to students.')
                ->visible(fn () => $this->record->status === OfferingStatus::Locked)
                ->action(function () {
                    try {
                        $this->record->publish();
                        Notification::make()->title('Offering published.')->success()->send();
                    } catch (\LogicException $e) {
                        Notification::make()->title($e->getMessage())->danger()->send();
                    }
                }),

            Actions\Action::make('weight_overview')
                ->label('Weight Overview')
                ->color('warning')
                ->icon('heroicon-o-scale')
                ->url(fn () => CourseOfferingResource::getUrl('weight-overview', ['record' => $this->record])),

            Actions\Action::make('score_normalization')
                ->label('Score Normalization')
                ->color('gray')
                ->icon('heroicon-o-adjustments-horizontal')
                ->url(fn () => CourseOfferingResource::getUrl('weight-breakdown', ['record' => $this->record])),

            Actions\Action::make('view_gradebook')
                ->label('View Gradebook')
                ->color('info')
                ->icon('heroicon-o-table-cells')
                ->url(fn () => CourseOfferingResource::getUrl('class-gradebook', ['record' => $this->record])),

            Actions\Action::make('export_grade_sheet')
                ->label('Export Grade Sheet')
                ->color('gray')
                ->icon('heroicon-o-arrow-down-tray')
                ->action(function () {
                    $code = $this->record->course->code ?? 'export';
                    $filename = "{$code}_grade_sheet.xlsx";

                    return Excel::download(new GradeSheetExport($this->record), $filename);
                }),

            Actions\Action::make('export_unza_marksheet')
                ->label('UNZA Mark Sheet')
                ->color('gray')
                ->icon('heroicon-o-document-arrow-down')
                ->action(function () {
                    $code = $this->record->course->code ?? 'export';
                    $filename = "{$code}_UNZA_mark_sheet.xlsx";

                    return Excel::download(new UNZAMarkSheetExport($this->record), $filename);
                }),

            Actions\Action::make('manage_links')
                ->label('Manage Links')
                ->color('success')
                ->icon('heroicon-o-link')
                ->url(fn () => CourseOfferingResource::getUrl('manage-links', ['record' => $this->record])),

            Actions\Action::make('duplicate')
                ->label('Duplicate')
                ->color('gray')
                ->icon('heroicon-o-document-duplicate')
                ->requiresConfirmation()
                ->modalHeading('Duplicate Course Offering')
                ->modalDescription('This will create a copy of this offering as a new draft, including its assessment groups and assessments.')
                ->action(function () {
                    $newOffering = $this->record->duplicate();
                    Notification::make()
                        ->title('Offering duplicated as draft.')
                        ->success()
                        ->send();

                    return redirect(CourseOfferingResource::getUrl('edit', ['record' => $newOffering]));
                }),

            Actions\DeleteAction::make()
                ->modalHeading('Delete Course Offering')
                ->modalDescription('Are you sure? This will remove the offering, its enrollments, and all grade data.'),
        ];
    }
}
