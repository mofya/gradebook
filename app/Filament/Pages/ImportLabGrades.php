<?php

namespace App\Filament\Pages;

use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\CourseOffering;
use App\Services\LabGradeImportService;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Throwable;

class ImportLabGrades extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-command-line';

    protected static string|\UnitEnum|null $navigationGroup = 'Import';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Import Lab Grades';

    protected string $view = 'filament.pages.import-lab-grades';

    public int $currentStep = 1;

    /** @var array<string, mixed> */
    public array $data = [];

    /** @var array<int, array{github_username: string, student_name: string, student_id: string, final_score: float, matched: bool}> */
    public array $previewData = [];

    /** @var array<int, array{github_username: string, final_score: float}> */
    public array $unmatchedRows = [];

    /** @var array<string, mixed> */
    public array $importResults = [];

    /** @var array<int, array<string, string>> */
    public array $parsedRows = [];

    public int $matchedCount = 0;

    public int $unmatchedCount = 0;

    public int $totalRows = 0;

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->schema([
                Select::make('courseOfferingId')
                    ->label('Course Offering')
                    ->options(
                        CourseOffering::query()
                            ->with(['course', 'semester.year'])
                            ->get()
                            ->mapWithKeys(fn ($co) => [
                                $co->id => $co->course->code.' - '.($co->semester->year->name ?? '').' '.$co->semester->name,
                            ])
                    )
                    ->searchable()
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn () => $this->data['assessmentId'] = null),

                Select::make('assessmentId')
                    ->label('Assessment')
                    ->options(function () {
                        $offeringId = $this->data['courseOfferingId'] ?? null;
                        if (! $offeringId) {
                            return [];
                        }

                        return Assessment::query()
                            ->whereHas('assessmentGroup', fn ($q) => $q->where('course_offering_id', $offeringId))
                            ->get()
                            ->mapWithKeys(fn ($a) => [$a->id => $a->name])
                            ->prepend('-- Create New Assessment --', 'new');
                    })
                    ->searchable()
                    ->required()
                    ->live()
                    ->visible(fn () => filled($this->data['courseOfferingId'] ?? null)),

                TextInput::make('newAssessmentName')
                    ->label('New Assessment Name')
                    ->placeholder('e.g., Lab 01 - Closures & Scope')
                    ->required()
                    ->visible(fn () => ($this->data['assessmentId'] ?? null) === 'new'),

                TextInput::make('newAssessmentWeight')
                    ->label('Weight')
                    ->helperText('Relative weight of this assessment within the group (e.g., 100).')
                    ->numeric()
                    ->default(100)
                    ->required()
                    ->visible(fn () => ($this->data['assessmentId'] ?? null) === 'new'),

                TextInput::make('newAssessmentNormalizedTo')
                    ->label('Normalized To')
                    ->helperText('Maximum normalized score for this assessment (e.g., 100).')
                    ->numeric()
                    ->default(100)
                    ->required()
                    ->visible(fn () => ($this->data['assessmentId'] ?? null) === 'new'),

                FileUpload::make('file')
                    ->label('Lab Grades CSV')
                    ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'text/plain'])
                    ->required()
                    ->disk('local')
                    ->directory('lab-grade-imports')
                    ->visible(fn () => filled($this->data['assessmentId'] ?? null)),
            ]);
    }

    public function parseAndPreview(): void
    {
        $courseOfferingId = $this->data['courseOfferingId'] ?? null;
        $assessmentId = $this->data['assessmentId'] ?? null;
        $file = $this->extractUploadedFilePath($this->data['file'] ?? null);

        if (! $courseOfferingId || ! $assessmentId || ! is_string($file) || blank($file)) {
            Notification::make()
                ->title('Please complete all fields before proceeding.')
                ->danger()
                ->send();

            return;
        }

        $filePath = storage_path('app/private/'.$file);
        if (! file_exists($filePath)) {
            Notification::make()
                ->title('The uploaded file is missing. Please re-upload.')
                ->danger()
                ->send();

            return;
        }

        try {
            $service = app(LabGradeImportService::class);
            $parsed = $service->parseCsv($filePath);
            $this->parsedRows = $parsed['rows'];

            $courseOffering = CourseOffering::findOrFail($courseOfferingId);
            $preview = $service->preview($this->parsedRows, $courseOffering);

            $this->previewData = [];
            foreach ($preview['matched'] as $match) {
                $this->previewData[] = [
                    'github_username' => $match['github_username'],
                    'student_name' => $match['student']->first_name.' '.$match['student']->last_name,
                    'student_id' => $match['student']->student_id_number ?? 'N/A',
                    'final_score' => (float) ($match['row']['Final Score (%)'] ?? 0),
                    'letter_grade' => $match['row']['Letter Grade'] ?? '',
                    'matched' => true,
                ];
            }

            $this->unmatchedRows = [];
            foreach ($preview['unmatched'] as $unmatched) {
                $this->unmatchedRows[] = [
                    'github_username' => $unmatched['github_username'],
                    'final_score' => (float) ($unmatched['row']['Final Score (%)'] ?? 0),
                    'letter_grade' => $unmatched['row']['Letter Grade'] ?? '',
                ];
            }

            $this->matchedCount = count($preview['matched']);
            $this->unmatchedCount = count($preview['unmatched']);
            $this->totalRows = $preview['total'];
            $this->currentStep = 2;

        } catch (Throwable $e) {
            Notification::make()
                ->title('Failed to parse CSV: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function confirmAndImport(): void
    {
        $courseOfferingId = $this->data['courseOfferingId'] ?? null;
        $assessmentId = $this->data['assessmentId'] ?? null;

        $courseOffering = CourseOffering::findOrFail($courseOfferingId);

        try {
            // Create or find the assessment
            $assessment = $this->resolveAssessment($courseOffering, $assessmentId);

            $service = app(LabGradeImportService::class);
            $this->importResults = $service->import(
                $courseOffering,
                $assessment,
                $this->parsedRows,
            );

            $this->currentStep = 3;

            Notification::make()
                ->title('Lab grades imported successfully!')
                ->success()
                ->send();

        } catch (Throwable $e) {
            Notification::make()
                ->title('Import failed: '.$e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function resolveAssessment(CourseOffering $courseOffering, string $assessmentId): Assessment
    {
        if ($assessmentId !== 'new') {
            return Assessment::findOrFail($assessmentId);
        }

        $name = $this->data['newAssessmentName'] ?? 'Lab Assessment';

        // Find or create a CA assessment group for labs
        $group = AssessmentGroup::firstOrCreate(
            [
                'course_offering_id' => $courseOffering->id,
                'name' => 'Labs',
                'type' => 'ca',
            ],
            [
                'weight_percentage' => 0,
                'weight_mode' => 'percentage',
                'sort_order' => AssessmentGroup::where('course_offering_id', $courseOffering->id)->max('sort_order') + 1,
            ]
        );

        $maxSort = Assessment::where('assessment_group_id', $group->id)->max('sort_order') ?? 0;

        $weight = (float) ($this->data['newAssessmentWeight'] ?? 100);
        $normalizedTo = (float) ($this->data['newAssessmentNormalizedTo'] ?? 100);

        return Assessment::create([
            'name' => $name,
            'assessment_group_id' => $group->id,
            'course_id' => $courseOffering->course_id,
            'weight' => $weight,
            'max_raw_score' => 100,
            'normalized_to' => $normalizedTo,
            'has_subsections' => true,
            'is_published' => false,
            'sort_order' => $maxSort + 1,
        ]);
    }

    public function resetImport(): void
    {
        $this->currentStep = 1;
        $this->data = [];
        $this->previewData = [];
        $this->unmatchedRows = [];
        $this->importResults = [];
        $this->parsedRows = [];
        $this->matchedCount = 0;
        $this->unmatchedCount = 0;
        $this->totalRows = 0;
        $this->form->fill();
    }

    protected function extractUploadedFilePath(mixed $file): ?string
    {
        if (is_string($file) && filled($file)) {
            return $file;
        }

        if (is_array($file)) {
            foreach ($file as $value) {
                if (is_string($value) && filled($value)) {
                    return $value;
                }
            }

            foreach ($file as $value) {
                if ($value instanceof TemporaryUploadedFile) {
                    $storedPath = $value->store('lab-grade-imports', 'local');

                    if (is_string($storedPath)) {
                        $this->data['file'] = [$storedPath];

                        return $storedPath;
                    }
                }
            }
        }

        return null;
    }
}
