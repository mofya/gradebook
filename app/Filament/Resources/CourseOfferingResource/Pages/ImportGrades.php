<?php

namespace App\Filament\Resources\CourseOfferingResource\Pages;

use App\Filament\Resources\CourseOfferingResource;
use App\Imports\GradesImport;
use App\Models\CourseOffering;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Schema;
use Maatwebsite\Excel\Facades\Excel;

class ImportGrades extends Page
{
    protected static string $resource = CourseOfferingResource::class;

    protected string $view = 'filament.resources.course-offering-resource.pages.import-grades';

    public CourseOffering $offering;

    /**
     * @var array<string, mixed>
     */
    public array $file = [];

    public function mount(int|string $record): void
    {
        $this->offering = CourseOffering::findOrFail($record);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                FileUpload::make('file')
                    ->label('CSV File')
                    ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'text/plain'])
                    ->required()
                    ->disk('local')
                    ->directory('grade-imports'),
            ]);
    }

    public function submit(): void
    {
        $uploadedFile = $this->getUploadedFilePath();

        if (! $uploadedFile) {
            Notification::make()
                ->title('Please upload a CSV file.')
                ->danger()
                ->send();

            return;
        }

        $import = new GradesImport($this->offering);
        Excel::import($import, storage_path('app/private/'.$uploadedFile));

        $this->file = [];
        $this->form->fill();

        Notification::make()
            ->title("Import complete: {$import->getImportedCount()} grades imported, {$import->getSkippedCount()} rows skipped.")
            ->success()
            ->send();
    }

    public function getTitle(): string
    {
        return 'Import Grades';
    }

    protected function getUploadedFilePath(): ?string
    {
        $first = collect($this->file)
            ->first(fn (mixed $value): bool => is_string($value) && filled($value));

        return is_string($first) ? $first : null;
    }
}
