<?php

namespace App\Services\Import;

use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeResult;
use App\Models\Student;
use App\Services\GradingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;

class CourseDataProcessor
{
    public function __construct(
        private CourseDataValidator $validator,
    ) {}

    /**
     * Import data into the system within a transaction.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, array{index: int, header: string, detected_role: string, confirmed_role: string, assessment_name: string|null, max_score: float|null}>  $columnMappings
     * @return array{students_created: int, students_found: int, enrollments_created: int, assessments_created: int, grades_imported: int, exam_scores_set: int, grades_resolved: int, errors: array<int, string>}
     */
    public function import(CourseOffering $courseOffering, array $rows, array $columnMappings, ?string $defaultProgram = null, ?int $defaultYearOfStudy = null): array
    {
        $results = [
            'students_created' => 0,
            'students_found' => 0,
            'enrollments_created' => 0,
            'assessments_created' => 0,
            'grades_imported' => 0,
            'exam_scores_set' => 0,
            'grades_resolved' => 0,
            'errors' => [],
        ];

        return DB::transaction(function () use ($courseOffering, $rows, $columnMappings, $defaultProgram, $defaultYearOfStudy, &$results) {
            $examColumn = collect($columnMappings)->firstWhere('confirmed_role', 'exam_score');
            $assessmentMap = $this->createAssessmentStructure($courseOffering, $columnMappings, $rows, $results);

            $columns = [
                'studentId' => collect($columnMappings)->firstWhere('confirmed_role', 'student_id'),
                'firstName' => collect($columnMappings)->firstWhere('confirmed_role', 'first_name'),
                'lastName' => collect($columnMappings)->firstWhere('confirmed_role', 'last_name'),
                'fullName' => collect($columnMappings)->firstWhere('confirmed_role', 'full_name'),
                'email' => collect($columnMappings)->firstWhere('confirmed_role', 'email'),
                'gender' => collect($columnMappings)->firstWhere('confirmed_role', 'gender'),
                'program' => collect($columnMappings)->firstWhere('confirmed_role', 'program'),
                'yearOfStudy' => collect($columnMappings)->firstWhere('confirmed_role', 'year_of_study'),
            ];

            foreach ($rows as $rowIndex => $row) {
                $result = $this->createOrUpdateStudent($row, $columns, $rowIndex, $defaultProgram, $defaultYearOfStudy);

                if ($result['error']) {
                    $results['errors'][] = $result['error'];
                }

                if (! $result['student']) {
                    continue;
                }

                if ($result['created']) {
                    $results['students_created']++;
                } else {
                    $results['students_found']++;
                }

                $student = $result['student'];

                $enrollment = Enrollment::where('student_id', $student->id)
                    ->where('course_offering_id', $courseOffering->id)
                    ->first();

                if (! $enrollment) {
                    $enrollment = Enrollment::create([
                        'student_id' => $student->id,
                        'course_offering_id' => $courseOffering->id,
                        'source' => 'bulk_import',
                        'status' => 'enrolled',
                    ]);
                    $results['enrollments_created']++;
                }

                $this->processAssessmentScores($enrollment, $row, $assessmentMap, $rowIndex, $results);

                if ($examColumn) {
                    $this->processExamScore($enrollment, $row, $examColumn, $rowIndex, $results);
                }
            }

            $gradingService = app(GradingService::class);
            $results['grades_resolved'] = $gradingService->resolveAllGrades($courseOffering);

            return $results;
        });
    }

    /**
     * Filter out trailing summary rows and non-data rows.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, array{index: int, header: string, detected_role: string, confirmed_role: string, assessment_name: string|null, max_score: float|null}>  $columnMappings
     * @return array{rows: array<int, array<int, mixed>>, skipped: int}
     */
    public function filterDataRows(array $rows, array $columnMappings): array
    {
        $studentIdColumn = collect($columnMappings)->firstWhere('confirmed_role', 'student_id');

        if (! $studentIdColumn) {
            return ['rows' => $rows, 'skipped' => 0];
        }

        $summaryKeywords = '/^(total|average|mean|statistics|summary|count|pass|fail|grand\s*total|std\.?\s*dev|min|max|median|mode)$/i';

        $filtered = [];
        $skipped = 0;
        $consecutiveBlanks = 0;

        foreach ($rows as $row) {
            $studentId = trim((string) ($row[$studentIdColumn['index']] ?? ''));

            if ($studentId === '') {
                $consecutiveBlanks++;
                if ($consecutiveBlanks >= 2) {
                    $skipped += count($rows) - count($filtered) - $consecutiveBlanks;

                    break;
                }

                continue;
            }

            $consecutiveBlanks = 0;

            if (preg_match($summaryKeywords, $studentId)) {
                $skipped++;

                continue;
            }

            $filtered[] = $row;
        }

        return ['rows' => $filtered, 'skipped' => $skipped];
    }

    /**
     * Detect which columns contain formulas in the first data row.
     *
     * @return array<int, int> Column indices that contain formulas
     */
    public function detectFormulaColumns(string $filePath, string $worksheetName, int $columnCount): array
    {
        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(false);

            if (method_exists($reader, 'setLoadSheetsOnly')) {
                $reader->setLoadSheetsOnly([$worksheetName]);
            }

            $reader->setReadFilter(new class implements IReadFilter
            {
                public function readCell($columnAddress, $row, $worksheetName = ''): bool
                {
                    return $row <= 2;
                }
            });

            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getSheetByName($worksheetName) ?? $spreadsheet->getSheet(0);

            $formulaColumns = [];

            for ($col = 0; $col < $columnCount; $col++) {
                $coordinate = Coordinate::stringFromColumnIndex($col + 1).'2';
                $cellValue = $sheet->getCell($coordinate)->getValue();

                if (is_string($cellValue) && str_starts_with($cellValue, '=')) {
                    $formulaColumns[] = $col;
                }
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return $formulaColumns;
        } catch (\Throwable $e) {
            Log::warning('Failed to detect formula columns', [
                'file' => $filePath,
                'worksheet' => $worksheetName,
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Extract assessment weights from a CA column formula.
     *
     * @return array<string, float>|null Column letter → weight, or null if no formula found
     */
    public function extractWeightsFromFormula(string $filePath, string $worksheetName, int $caColumnIndex): ?array
    {
        try {
            $reader = IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(false);

            if (method_exists($reader, 'setLoadSheetsOnly')) {
                $reader->setLoadSheetsOnly([$worksheetName]);
            }

            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getSheetByName($worksheetName) ?? $spreadsheet->getSheet(0);

            $colLetter = Coordinate::stringFromColumnIndex($caColumnIndex + 1);
            $formula = $sheet->getCell("{$colLetter}2")->getValue();

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            if (! is_string($formula) || ! str_starts_with($formula, '=')) {
                return null;
            }

            $weights = [];
            if (preg_match_all('/([A-Z]+)\d+\s*\*\s*([\d.]+)/', $formula, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $weights[$match[1]] = (float) $match[2];
                }
            }

            return ! empty($weights) ? $weights : null;
        } catch (\Throwable $e) {
            Log::warning('Failed to extract weights from formula', [
                'file' => $filePath,
                'worksheet' => $worksheetName,
                'column_index' => $caColumnIndex,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * @return array<int, Assessment>
     */
    private function createAssessmentStructure(
        CourseOffering $courseOffering,
        array &$columnMappings,
        array $rows,
        array &$results
    ): array {
        $caColumns = collect($columnMappings)->where('confirmed_role', 'ca_assessment');
        $assessmentMap = [];

        if ($caColumns->isEmpty()) {
            return $assessmentMap;
        }

        $group = AssessmentGroup::firstOrCreate(
            [
                'course_offering_id' => $courseOffering->id,
                'type' => 'ca',
            ],
            [
                'name' => 'Continuous Assessment',
                'weight_percentage' => $courseOffering->ca_weight,
                'weight_mode' => 'percentage',
                'sort_order' => 0,
            ]
        );

        $columnMappings = $this->validator->inferMaxScores($rows, $columnMappings);
        $caColumns = collect($columnMappings)->where('confirmed_role', 'ca_assessment');

        $sumMaxScores = $caColumns->sum('max_score') ?: $caColumns->count();
        $hasExplicitWeights = $caColumns->contains(fn ($c) => isset($c['ca_weight']));

        $weightSum = $hasExplicitWeights
            ? $caColumns->filter(fn ($c) => isset($c['ca_weight']))->sum('ca_weight')
            : 0;
        $weightScale = ($hasExplicitWeights && $weightSum > 0) ? 100 / $weightSum : 1;

        foreach ($caColumns as $sortIndex => $col) {
            $maxScore = $col['max_score'] ?? 100;

            if ($hasExplicitWeights && isset($col['ca_weight'])) {
                $normalizedTo = round((float) $col['ca_weight'] * $weightScale, 2);
            } else {
                $normalizedTo = $sumMaxScores > 0
                    ? round(($maxScore / $sumMaxScores) * 100, 2)
                    : round(100 / $caColumns->count(), 2);
            }

            $assessment = Assessment::firstOrCreate(
                [
                    'assessment_group_id' => $group->id,
                    'course_id' => $courseOffering->course_id,
                    'name' => $col['assessment_name'] ?? $col['header'],
                ],
                [
                    'weight' => $normalizedTo,
                    'max_raw_score' => $maxScore,
                    'normalized_to' => $normalizedTo,
                    'sort_order' => $sortIndex,
                ]
            );

            $assessmentMap[$col['index']] = $assessment;
            $results['assessments_created']++;
        }

        return $assessmentMap;
    }

    /**
     * @return array{student: Student|null, error: string|null, created: bool}
     */
    private function createOrUpdateStudent(
        array $row,
        array $columns,
        int $rowIndex,
        ?string $defaultProgram,
        ?int $defaultYearOfStudy
    ): array {
        $studentIdValue = $columns['studentId'] ? trim((string) ($row[$columns['studentId']['index']] ?? '')) : '';

        if ($studentIdValue === '') {
            return ['student' => null, 'error' => "Row {$rowIndex}: Missing student ID, skipped.", 'created' => false];
        }

        $warning = null;
        if (strlen($studentIdValue) < 3) {
            $warning = "Row {$rowIndex}: Student ID '{$studentIdValue}' is unusually short.";
        }

        if ($columns['fullName']) {
            $fullName = trim((string) ($row[$columns['fullName']['index']] ?? ''));
            $parts = preg_split('/\s+/', $fullName, 2);
            $lastName = $parts[0] ?? '';
            $firstName = $parts[1] ?? '';
        } else {
            $firstName = $columns['firstName'] ? trim((string) ($row[$columns['firstName']['index']] ?? '')) : '';
            $lastName = $columns['lastName'] ? trim((string) ($row[$columns['lastName']['index']] ?? '')) : '';
        }

        $email = $columns['email'] ? trim((string) ($row[$columns['email']['index']] ?? '')) : '';
        $gender = $columns['gender'] ? trim((string) ($row[$columns['gender']['index']] ?? '')) : null;
        $program = $columns['program'] ? trim((string) ($row[$columns['program']['index']] ?? '')) : null;
        $yearOfStudy = $columns['yearOfStudy'] ? trim((string) ($row[$columns['yearOfStudy']['index']] ?? '')) : null;

        if (blank($program) && $defaultProgram !== null) {
            $program = $defaultProgram;
        }
        if (blank($yearOfStudy) && $defaultYearOfStudy !== null) {
            $yearOfStudy = (string) $defaultYearOfStudy;
        }

        $student = Student::where('student_id_number', $studentIdValue)->first();

        if ($student) {
            return ['student' => $student, 'error' => $warning, 'created' => false];
        }

        $resolvedEmail = $email ?: $studentIdValue.'@placeholder.unza.zm';
        $existingByEmail = Student::where('email', $resolvedEmail)->first();

        if ($existingByEmail) {
            return ['student' => null, 'error' => "Row {$rowIndex}: Email '{$resolvedEmail}' already belongs to student {$existingByEmail->student_id_number}, skipped.", 'created' => false];
        }

        $student = Student::create([
            'student_id_number' => $studentIdValue,
            'first_name' => $firstName ?: 'Unknown',
            'last_name' => $lastName ?: 'Unknown',
            'email' => $resolvedEmail,
            'gender' => $gender,
            'program' => $program,
            'year_of_study' => is_numeric($yearOfStudy) ? (int) $yearOfStudy : null,
        ]);

        return ['student' => $student, 'error' => $warning, 'created' => true];
    }

    private function processAssessmentScores(Enrollment $enrollment, array $row, array $assessmentMap, int $rowIndex, array &$results): void
    {
        foreach ($assessmentMap as $colIndex => $assessment) {
            $rawValue = $row[$colIndex] ?? null;

            if ($rawValue === null || trim((string) $rawValue) === '') {
                continue;
            }

            if (! is_numeric($rawValue)) {
                $results['errors'][] = "Row {$rowIndex}: Non-numeric score '{$rawValue}' for {$assessment->name}, skipped.";

                continue;
            }

            $rawScore = (float) $rawValue;

            $gradeResult = GradeResult::updateOrCreate(
                [
                    'enrollment_id' => $enrollment->id,
                    'assessment_id' => $assessment->id,
                ],
                [
                    'raw_score' => $rawScore,
                    'source' => 'bulk_import',
                ]
            );

            $normalized = $gradeResult->calculateNormalizedScore();
            if ($normalized !== null) {
                $gradeResult->update(['normalized_score' => $normalized]);
            }

            $results['grades_imported']++;
        }
    }

    private function processExamScore(Enrollment $enrollment, array $row, array $examColumn, int $rowIndex, array &$results): void
    {
        $examValue = $row[$examColumn['index']] ?? null;

        if ($examValue !== null && trim((string) $examValue) !== '' && ! is_numeric($examValue)) {
            $results['errors'][] = "Row {$rowIndex}: Non-numeric exam score '{$examValue}', skipped.";

            return;
        }

        if ($examValue === null || trim((string) $examValue) === '') {
            return;
        }

        $examScore = (float) $examValue;
        $examDenominator = $examColumn['max_score'] ?? 100;

        if ($examDenominator > 0 && $examDenominator != 100) {
            $examScore = round(($examScore / $examDenominator) * 100, 2);
        }

        $enrollment->update(['exam_score' => $examScore]);
        $results['exam_scores_set']++;
    }
}
