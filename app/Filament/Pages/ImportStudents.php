<?php

namespace App\Filament\Pages;

use App\Exports\StudentImportTemplate;
use App\Imports\StudentsImport;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use BackedEnum;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportStudents extends Page
{
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-arrow-up-on-square';

    protected static string|\UnitEnum|null $navigationGroup = 'Import';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.import-students';

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->schema([
                Section::make('Course Offering')
                    ->description('Select the course offering to enroll imported students into.')
                    ->icon('heroicon-o-academic-cap')
                    ->schema([
                        Forms\Components\Select::make('course_offering_id')
                            ->label('Course Offering')
                            ->options(
                                CourseOffering::query()
                                    ->with(['course', 'semester.year'])
                                    ->get()
                                    ->mapWithKeys(fn (CourseOffering $offering) => [
                                        $offering->id => $offering->course->code.' - '.$offering->course->name.' ('.$offering->semester->year->name.', '.$offering->semester->name.')',
                                    ])
                            )
                            ->searchable()
                            ->required()
                            ->helperText('Students will be enrolled into this course offering after import.'),
                    ])
                    ->columnSpanFull(),

                Section::make('Upload File')
                    ->description('Upload an Excel file with student data.')
                    ->icon('heroicon-o-arrow-up-on-square')
                    ->schema([
                        Forms\Components\FileUpload::make('file')
                            ->label('Student Excel File')
                            ->helperText('Required columns: first_name, last_name, email. Optional: student_id, gender, program, year_of_study, github_username')
                            ->required()
                            ->disk('local')
                            ->directory('student-imports')
                            ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel']),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public function submit(): void
    {
        $data = $this->form->getState();

        $file = $data['file'] ?? null;

        if (! is_string($file) || blank($file)) {
            Notification::make()
                ->title('Please upload a valid file.')
                ->danger()
                ->send();

            return;
        }

        $filePath = storage_path('app/private/'.$file);

        // Validate headers before importing
        $headers = $this->readHeaders($filePath);

        if ($headers === null) {
            Notification::make()
                ->title('Could not read the file. Please check the format.')
                ->danger()
                ->send();

            return;
        }

        $validation = StudentsImport::validateHeaders($headers);

        if (! $validation['valid']) {
            $missing = implode(', ', $validation['missing']);

            Notification::make()
                ->title('Missing required columns: '.$missing)
                ->body('Expected columns: first_name, last_name, email (required); student_id, gender, program, year_of_study, github_username (optional). Download the template for the correct format.')
                ->danger()
                ->persistent()
                ->send();

            return;
        }

        $courseOfferingId = $data['course_offering_id'];

        $importer = new StudentsImport;
        Excel::import($importer, $filePath);

        $imported = $importer->getImportedCount();
        $skipped = $importer->getSkippedCount();
        $importedStudents = $importer->getImportedStudents();

        // Enroll imported students into the selected course offering
        $enrolled = 0;
        foreach ($importedStudents as $student) {
            Enrollment::firstOrCreate(
                [
                    'student_id' => $student->id,
                    'course_offering_id' => $courseOfferingId,
                ],
                [
                    'source' => 'import',
                    'status' => 'active',
                ]
            );
            $enrolled++;
        }

        $this->form->fill();

        if ($imported === 0 && $skipped > 0) {
            Notification::make()
                ->title("No students imported. {$skipped} rows skipped due to invalid data.")
                ->warning()
                ->send();

            return;
        }

        $message = "{$imported} students imported, {$enrolled} enrolled.";
        if ($skipped > 0) {
            $message .= " {$skipped} rows skipped.";
        }

        Notification::make()
            ->title($message)
            ->success()
            ->send();
    }

    /**
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadTemplate()
    {
        return Excel::download(new StudentImportTemplate, 'student_import_template.xlsx');
    }

    /**
     * Read the first row of headers from an Excel file.
     *
     * @return array<int, string>|null
     */
    protected function readHeaders(string $filePath): ?array
    {
        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(true);
            $spreadsheet = $reader->load($filePath);
            $firstRow = $spreadsheet->getActiveSheet()->toArray()[0] ?? null;

            if (! is_array($firstRow)) {
                return null;
            }

            return array_filter($firstRow, fn ($v) => $v !== null && $v !== '');
        } catch (\Throwable) {
            return null;
        }
    }
}
