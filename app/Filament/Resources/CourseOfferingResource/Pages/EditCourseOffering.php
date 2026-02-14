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

            Actions\Action::make('duplicate')
                ->label('Duplicate')
                ->color('gray')
                ->icon('heroicon-o-document-duplicate')
                ->requiresConfirmation()
                ->action(function () {
                    $newOffering = $this->record->duplicate();
                    Notification::make()
                        ->title('Offering duplicated as draft.')
                        ->success()
                        ->send();

                    return redirect(CourseOfferingResource::getUrl('edit', ['record' => $newOffering]));
                }),

            Actions\DeleteAction::make(),
        ];
    }
}
