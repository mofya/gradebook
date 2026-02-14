<?php

namespace App\Filament\Pages;

use App\Exports\CourseDataImportTemplate;
use App\Models\CourseOffering;
use App\Services\CourseDataImportService;
use BackedEnum;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

class ImportCourseData extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-arrow-up-on-square-stack';

    protected static string|\UnitEnum|null $navigationGroup = 'Import';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Import Course Data';

    protected string $view = 'filament.pages.import-course-data';

    public int $currentStep = 1;

    /**
     * @var array<string, mixed>
     */
    public array $data = [];

    /** @var array<int, array{index: int, header: string, detected_role: string, confirmed_role: string, assessment_name: string|null, max_score: float|null}> */
    public array $columnMappings = [];

    /** @var array<int, array<int, mixed>> */
    public array $previewRows = [];

    /** @var array<string, mixed> */
    public array $importResults = [];

    /** @var array<string, string> */
    public array $availableSheets = [];

    /** @var array<int, array{column_index: int, header: string, detected_weight: float|null, ca_points: float|null, override_weight: float|null}> */
    public array $weightConfig = [];

    /** @var array<int, string> */
    public array $preflightInfo = [];

    public ?string $recommendedSheet = null;

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
                    ->visible(fn () => $this->currentStep <= 2),

                FileUpload::make('file')
                    ->label('Excel File')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/vnd.ms-excel',
                        'text/csv',
                    ])
                    ->required()
                    ->disk('local')
                    ->directory('course-data-imports')
                    ->visible(fn () => $this->currentStep <= 2 && filled($this->data['courseOfferingId'] ?? null)),

                Select::make('sheetName')
                    ->label('Worksheet')
                    ->options($this->availableSheets)
                    ->helperText(fn (): ?string => count($this->availableSheets) > 1
                        ? 'This workbook has multiple worksheets. Select the worksheet to import.'
                        : null)
                    ->visible(fn (): bool => $this->currentStep <= 2 && filled($this->data['file'] ?? null) && count($this->availableSheets) > 0),
            ]);
    }

    public function updatedDataFile(mixed $file): void
    {
        $this->availableSheets = [];
        $this->data['sheetName'] = null;
        $this->recommendedSheet = null;

        $relativePath = $this->extractUploadedFilePath($file);

        if (! is_string($relativePath) || blank($relativePath)) {
            return;
        }

        $filePath = storage_path('app/private/'.$relativePath);
        if (! file_exists($filePath)) {
            return;
        }

        try {
            $sheetNames = $this->getWorksheetNames($filePath);
            $service = app(CourseDataImportService::class);
            $reportFlags = $service->flagReportSheets($sheetNames);

            $this->availableSheets = collect($sheetNames)
                ->mapWithKeys(function (string $sheetName) use ($reportFlags): array {
                    $label = $sheetName;
                    if ($reportFlags[$sheetName] ?? false) {
                        $label .= ' (Report)';
                    }

                    return [$sheetName => $label];
                })
                ->all();

            // Resolve course code for auto-select
            $courseCode = null;
            $courseOfferingId = $this->data['courseOfferingId'] ?? null;
            if ($courseOfferingId) {
                $offering = CourseOffering::with('course')->find($courseOfferingId);
                $courseCode = $offering?->course?->code;
            }

            $recommended = $service->autoSelectSheet($sheetNames, $courseCode);
            $this->recommendedSheet = $recommended;

            if (count($sheetNames) === 1) {
                $this->data['sheetName'] = $sheetNames[0];
            } elseif ($recommended) {
                $this->data['sheetName'] = $recommended;
                $this->availableSheets[$recommended] = $recommended.' (Recommended)';
            }
        } catch (Throwable $e) {
            Notification::make()
                ->title('Could not read worksheet names from the uploaded file.')
                ->danger()
                ->send();
        }
    }

    public function updatedDataSheetName(?string $sheetName): void
    {
        if (! is_string($sheetName) || blank($sheetName)) {
            return;
        }

        $service = app(CourseDataImportService::class);
        $flags = $service->flagReportSheets(array_keys($this->availableSheets));

        if ($flags[$sheetName] ?? false) {
            Notification::make()
                ->title("'{$sheetName}' appears to be a report sheet, not a data sheet. Verify before importing.")
                ->warning()
                ->send();
        }
    }

    public function parseFile(): void
    {
        $courseOfferingId = $this->data['courseOfferingId'] ?? null;
        $file = $this->extractUploadedFilePath($this->data['file'] ?? null);
        $selectedSheet = $this->data['sheetName'] ?? null;

        if (! $courseOfferingId || ! is_string($file) || blank($file)) {
            Notification::make()
                ->title('Please select a course offering and upload a file.')
                ->danger()
                ->send();

            return;
        }

        $filePath = storage_path('app/private/'.$file);

        if (! file_exists($filePath) || filesize($filePath) === 0) {
            Notification::make()
                ->title('The uploaded file is missing or empty. Please re-upload the file.')
                ->danger()
                ->send();

            return;
        }

        try {
            $sheetNames = $this->getWorksheetNames($filePath);
        } catch (Throwable $e) {
            Notification::make()
                ->title('Could not read the uploaded file. Please ensure it is a valid Excel or CSV file.')
                ->danger()
                ->send();

            return;
        }

        $this->availableSheets = collect($sheetNames)
            ->mapWithKeys(fn (string $sheetName): array => [$sheetName => $sheetName])
            ->all();

        if (count($sheetNames) > 1 && (! is_string($selectedSheet) || ! in_array($selectedSheet, $sheetNames, true))) {
            Notification::make()
                ->title('Please choose which worksheet to parse.')
                ->warning()
                ->send();

            return;
        }

        if (! is_string($selectedSheet) || blank($selectedSheet)) {
            $selectedSheet = $sheetNames[0] ?? null;
            if (is_string($selectedSheet)) {
                $this->data['sheetName'] = $selectedSheet;
            }
        }

        if (! is_string($selectedSheet) || blank($selectedSheet)) {
            Notification::make()
                ->title('No worksheet found in the uploaded file.')
                ->danger()
                ->send();

            return;
        }

        try {
            $data = $this->loadRowsFromWorksheet($filePath, $selectedSheet);
        } catch (Throwable $e) {
            Notification::make()
                ->title('Could not read data from the file. Please ensure it is a valid Excel or CSV file.')
                ->danger()
                ->send();

            return;
        }

        if (count($data) < 2) {
            Notification::make()
                ->title('The file appears to be empty or has no data rows.')
                ->danger()
                ->send();

            return;
        }

        $headers = $data[0];
        $rows = array_slice($data, 1);

        $service = app(CourseDataImportService::class);
        $this->columnMappings = $service->parseHeaders($headers);

        $validation = $service->validateColumnMappings($this->columnMappings);

        if (! $validation['valid']) {
            Notification::make()
                ->title('Some required columns were not auto-detected. Please review and assign them below.')
                ->warning()
                ->send();
        }

        $this->previewRows = array_slice($rows, 0, 5);
        $this->currentStep = 3;
    }

    public function updatedColumnMappings(mixed $value, string $key): void
    {
        if (str_ends_with($key, '.confirmed_role') && $value !== 'ca_assessment') {
            $index = (int) explode('.', $key)[0];

            if (isset($this->columnMappings[$index])) {
                $this->columnMappings[$index]['assessment_name'] = null;
                $this->columnMappings[$index]['max_score'] = null;
            }
        }
    }

    public function proceedToWeightConfig(): void
    {
        $validation = app(CourseDataImportService::class)->validateColumnMappings($this->columnMappings);
        if (! $validation['valid']) {
            Notification::make()
                ->title('Required column mappings are missing. Fix them before proceeding.')
                ->danger()
                ->send();

            return;
        }

        // Build weight config from CA columns
        $caColumns = collect($this->columnMappings)->where('confirmed_role', 'ca_assessment');

        if ($caColumns->isEmpty()) {
            // No CA columns, skip weight config
            $this->weightConfig = [];
            $this->currentStep = 4;
            $this->runExtendedPreflight();

            return;
        }

        // Try to extract weights from CA formula
        $file = $this->extractUploadedFilePath($this->data['file'] ?? null);
        $selectedSheet = $this->data['sheetName'] ?? null;
        $detectedWeights = null;

        if (is_string($file) && filled($file) && is_string($selectedSheet)) {
            $filePath = storage_path('app/private/'.$file);
            $service = app(CourseDataImportService::class);

            // Find a CA total column to extract formula from
            $caTotalCol = collect($this->columnMappings)->first(function ($m) {
                return $m['confirmed_role'] === 'skip'
                    && preg_match('/^ca\s*[\/(]/i', $m['header']);
            });

            if ($caTotalCol) {
                $detectedWeights = $service->extractWeightsFromFormula($filePath, $selectedSheet, $caTotalCol['index']);
            }
        }

        $this->weightConfig = [];
        $sumMaxScores = $caColumns->sum('max_score') ?: $caColumns->count();

        foreach ($caColumns as $col) {
            $maxScore = $col['max_score'] ?? 1;
            $defaultWeight = $sumMaxScores > 0
                ? round(($maxScore / $sumMaxScores) * 100, 2)
                : round(100 / $caColumns->count(), 2);

            // Try to match detected formula weight to this column
            $formulaWeight = null;
            if ($detectedWeights) {
                $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col['index'] + 1);
                $formulaWeight = $detectedWeights[$colLetter] ?? null;
                if ($formulaWeight !== null) {
                    // Formula weights are typically decimal multipliers (e.g. 0.025 = 2.5%)
                    $formulaWeight = round($formulaWeight * 100, 2);
                }
            }

            $this->weightConfig[] = [
                'column_index' => $col['index'],
                'header' => $col['assessment_name'] ?? $col['header'],
                'max_score' => $maxScore,
                'detected_weight' => $formulaWeight,
                'ca_points' => $defaultWeight,
                'override_weight' => null,
            ];
        }

        $this->currentStep = 4;
    }

    public function confirmAndImport(): void
    {
        $courseOfferingId = $this->data['courseOfferingId'] ?? null;
        $file = $this->extractUploadedFilePath($this->data['file'] ?? null);
        $selectedSheet = $this->data['sheetName'] ?? null;

        $courseOffering = $courseOfferingId ? CourseOffering::find($courseOfferingId) : null;

        if (! $courseOffering) {
            Notification::make()
                ->title('Course offering not found.')
                ->danger()
                ->send();

            return;
        }

        if (! is_string($file) || blank($file)) {
            Notification::make()
                ->title('Please upload a valid file.')
                ->danger()
                ->send();

            return;
        }

        $filePath = storage_path('app/private/'.$file);

        if (! file_exists($filePath) || filesize($filePath) === 0) {
            Notification::make()
                ->title('The uploaded file is missing or empty. Please re-upload the file.')
                ->danger()
                ->send();

            return;
        }

        try {
            $sheetNames = $this->getWorksheetNames($filePath);
        } catch (Throwable $e) {
            Notification::make()
                ->title('Could not read the uploaded file. Please re-upload a valid Excel or CSV file.')
                ->danger()
                ->send();

            return;
        }

        if (! is_string($selectedSheet) || ! in_array($selectedSheet, $sheetNames, true)) {
            Notification::make()
                ->title('Please choose a valid worksheet before importing.')
                ->danger()
                ->send();

            return;
        }

        $validation = app(CourseDataImportService::class)->validateColumnMappings($this->columnMappings);
        if (! $validation['valid']) {
            Notification::make()
                ->title('Import blocked: required column mappings are missing.')
                ->danger()
                ->send();

            foreach ($validation['errors'] as $error) {
                Notification::make()
                    ->title($error)
                    ->danger()
                    ->send();
            }

            return;
        }

        // Apply weight overrides to column mappings
        foreach ($this->weightConfig as $wc) {
            $overrideWeight = $wc['override_weight'] ?? null;
            if ($overrideWeight !== null && $overrideWeight !== '') {
                foreach ($this->columnMappings as &$mapping) {
                    if ($mapping['index'] === $wc['column_index'] && $mapping['confirmed_role'] === 'ca_assessment') {
                        $mapping['max_score'] = (float) $overrideWeight;
                    }
                }
                unset($mapping);
            }
        }

        try {
            $data = $this->loadRowsFromWorksheet($filePath, $selectedSheet);
        } catch (Throwable $e) {
            Notification::make()
                ->title('Could not read data from the file. Please re-upload a valid Excel or CSV file.')
                ->danger()
                ->send();

            return;
        }

        $rawRows = array_slice($data, 1);

        $service = app(CourseDataImportService::class);

        // Filter out trailing summary rows and non-data rows
        $filterResult = $service->filterDataRows($rawRows, $this->columnMappings);
        $rows = $filterResult['rows'];

        if ($filterResult['skipped'] > 0) {
            Notification::make()
                ->title($filterResult['skipped'].' trailing/summary row(s) were automatically excluded.')
                ->info()
                ->send();
        }

        $preflight = $service->extendedPreflight($rows, $this->columnMappings);
        foreach ($preflight['warnings'] as $warning) {
            Notification::make()
                ->title($warning)
                ->warning()
                ->send();
        }

        if (! $preflight['valid']) {
            Notification::make()
                ->title('Import blocked by preflight checks. Fix the reported issues and try again.')
                ->danger()
                ->send();

            foreach (array_slice($preflight['errors'], 0, 8) as $error) {
                Notification::make()
                    ->title($error)
                    ->danger()
                    ->send();
            }

            if (count($preflight['errors']) > 8) {
                Notification::make()
                    ->title('Additional preflight errors were found. Please correct your sheet and retry.')
                    ->danger()
                    ->send();
            }

            return;
        }

        $this->preflightInfo = $preflight['info'] ?? [];

        $this->importResults = $service->import($courseOffering, $rows, $this->columnMappings);
        $this->currentStep = 5;

        Notification::make()
            ->title('Import completed successfully!')
            ->success()
            ->send();
    }

    protected function runExtendedPreflight(): void
    {
        $file = $this->extractUploadedFilePath($this->data['file'] ?? null);
        $selectedSheet = $this->data['sheetName'] ?? null;

        if (! is_string($file) || blank($file) || ! is_string($selectedSheet)) {
            return;
        }

        $filePath = storage_path('app/private/'.$file);

        try {
            $data = $this->loadRowsFromWorksheet($filePath, $selectedSheet);
            $rawRows = array_slice($data, 1);
            $service = app(CourseDataImportService::class);
            $rows = $service->filterDataRows($rawRows, $this->columnMappings)['rows'];
            $preflight = $service->extendedPreflight($rows, $this->columnMappings);

            foreach ($preflight['warnings'] as $warning) {
                Notification::make()->title($warning)->warning()->send();
            }

            $this->preflightInfo = $preflight['info'] ?? [];
        } catch (Throwable) {
            // Silently skip extended preflight on error
        }
    }

    /**
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function downloadTemplate()
    {
        return Excel::download(new CourseDataImportTemplate, 'course_data_import_template.xlsx');
    }

    public function resetImport(): void
    {
        $this->currentStep = 1;
        $this->data = [];
        $this->columnMappings = [];
        $this->previewRows = [];
        $this->importResults = [];
        $this->availableSheets = [];
        $this->weightConfig = [];
        $this->preflightInfo = [];
        $this->recommendedSheet = null;
        $this->form->fill();
    }

    /**
     * Available column role options for the mapping dropdown.
     *
     * @return array<string, string>
     */
    public function getColumnRoleOptions(): array
    {
        return [
            'skip' => 'Skip',
            'student_id' => 'Student ID',
            'first_name' => 'First Name',
            'last_name' => 'Last Name',
            'full_name' => 'Full Name',
            'email' => 'Email',
            'gender' => 'Gender',
            'program' => 'Program',
            'ca_assessment' => 'CA Assessment',
            'exam_score' => 'Exam Score',
        ];
    }

    /**
     * @return array<int, string>
     */
    protected function getWorksheetNames(string $filePath): array
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);

        if (! method_exists($reader, 'listWorksheetNames')) {
            return ['Worksheet'];
        }

        /** @var array<int, string> $sheetNames */
        $sheetNames = $reader->listWorksheetNames($filePath);

        return $sheetNames;
    }

    /**
     * @return array<int, array<int, mixed>>
     */
    protected function loadRowsFromWorksheet(string $filePath, string $worksheetName): array
    {
        $reader = IOFactory::createReaderForFile($filePath);
        $reader->setReadDataOnly(true);

        if (method_exists($reader, 'setLoadSheetsOnly')) {
            $reader->setLoadSheetsOnly([$worksheetName]);
        }

        $spreadsheet = $reader->load($filePath);
        $sheet = $spreadsheet->getSheetByName($worksheetName) ?? $spreadsheet->getSheet(0);

        /** @var array<int, array<int, mixed>> $rows */
        $rows = $sheet->toArray(null, false, false, false);

        $spreadsheet->disconnectWorksheets();
        unset($spreadsheet);

        return $rows;
    }

    protected function extractUploadedFilePath(mixed $file): ?string
    {
        if (is_string($file) && filled($file)) {
            return $file;
        }

        if (is_array($file)) {
            // Check for already-stored string paths first
            foreach ($file as $value) {
                if (is_string($value) && filled($value)) {
                    return $value;
                }
            }

            // Handle Livewire TemporaryUploadedFile objects (pre-getState)
            foreach ($file as $value) {
                if ($value instanceof TemporaryUploadedFile) {
                    $storedPath = $value->store('course-data-imports', 'local');

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
