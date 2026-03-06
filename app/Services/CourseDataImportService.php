<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeResult;
use App\Models\Student;
use Illuminate\Support\Facades\DB;

class CourseDataImportService
{
    /**
     * Validate that column mappings meet minimum requirements.
     *
     * @param  array<int, array{index: int, header: string, detected_role: string, confirmed_role: string, assessment_name: string|null, max_score: float|null}>  $columnMappings
     * @return array{valid: bool, errors: array<int, string>}
     */
    public function validateColumnMappings(array $columnMappings): array
    {
        $errors = [];
        $roles = collect($columnMappings)->pluck('confirmed_role');

        // Student ID — always required, exactly one
        $studentIdCount = $roles->filter(fn ($r) => $r === 'student_id')->count();
        if ($studentIdCount === 0) {
            $errors[] = 'No Student ID column detected. Exactly one column must be mapped to Student ID.';
        } elseif ($studentIdCount > 1) {
            $errors[] = 'Multiple Student ID columns detected. Exactly one column should be mapped to Student ID.';
        }

        // Name — either full_name OR (first_name + last_name)
        $hasFullName = $roles->contains('full_name');
        $hasFirstName = $roles->contains('first_name');
        $hasLastName = $roles->contains('last_name');

        if ($hasFullName) {
            if ($roles->filter(fn ($r) => $r === 'full_name')->count() > 1) {
                $errors[] = 'Multiple Full Name columns detected. Only one should be mapped.';
            }
        } elseif (! $hasFirstName && ! $hasLastName) {
            $errors[] = 'No name column detected. Map a Full Name, or First Name + Last Name column.';
        } else {
            if (! $hasFirstName) {
                $errors[] = 'No First Name column detected. Either map First Name or use Full Name.';
            }
            if (! $hasLastName) {
                $errors[] = 'No Last Name column detected. Either map Last Name or use Full Name.';
            }
        }

        // Email — required, exactly one
        $emailCount = $roles->filter(fn ($r) => $r === 'email')->count();
        if ($emailCount === 0) {
            $errors[] = 'No Email column detected. Exactly one column must be mapped to Email.';
        } elseif ($emailCount > 1) {
            $errors[] = 'Multiple Email columns detected. Exactly one column should be mapped to Email.';
        }

        // Exam — warn if missing or multiple
        $examCount = $roles->filter(fn ($r) => $r === 'exam_score')->count();
        if ($examCount === 0) {
            $errors[] = 'Warning: No Exam Score column detected.';
        } elseif ($examCount > 1) {
            $errors[] = 'Warning: Multiple Exam Score columns detected ('.
                $examCount.'). Only the first will be used — consider skipping the duplicate.';
        }

        return [
            'valid' => collect($errors)->filter(fn ($e) => ! str_starts_with($e, 'Warning:'))->isEmpty(),
            'errors' => $errors,
        ];
    }

    /**
     * Run blocking checks before committing import data.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, array{index: int, header: string, detected_role: string, confirmed_role: string, assessment_name: string|null, max_score: float|null}>  $columnMappings
     * @return array{valid: bool, errors: array<int, string>, warnings: array<int, string>}
     */
    public function preflight(array $rows, array $columnMappings): array
    {
        $mappingValidation = $this->validateColumnMappings($columnMappings);
        $blockingMappingErrors = collect($mappingValidation['errors'])
            ->filter(fn (string $error): bool => ! str_starts_with($error, 'Warning:'))
            ->values()
            ->all();
        $mappingWarnings = collect($mappingValidation['errors'])
            ->filter(fn (string $error): bool => str_starts_with($error, 'Warning:'))
            ->values()
            ->all();

        if (count($blockingMappingErrors) > 0) {
            return [
                'valid' => false,
                'errors' => $blockingMappingErrors,
                'warnings' => $mappingWarnings,
            ];
        }

        $errors = [];
        $warnings = $mappingWarnings;

        $studentIdColumn = collect($columnMappings)->firstWhere('confirmed_role', 'student_id');
        $firstNameColumn = collect($columnMappings)->firstWhere('confirmed_role', 'first_name');
        $lastNameColumn = collect($columnMappings)->firstWhere('confirmed_role', 'last_name');
        $fullNameColumn = collect($columnMappings)->firstWhere('confirmed_role', 'full_name');
        $emailColumn = collect($columnMappings)->firstWhere('confirmed_role', 'email');
        $examColumn = collect($columnMappings)->firstWhere('confirmed_role', 'exam_score');
        $caColumns = collect($columnMappings)->where('confirmed_role', 'ca_assessment');

        if ($caColumns->isEmpty()) {
            $warnings[] = 'No CA assessment columns are mapped. Import will only use exam scores.';
        }

        if (! $examColumn) {
            $warnings[] = 'No exam column is mapped. Final grades may rely only on CA scores.';
        }

        $seenStudentIds = [];
        $seenEmails = [];

        foreach ($rows as $rowIndex => $row) {
            $sheetRow = $rowIndex + 2;

            $studentId = $studentIdColumn ? trim((string) ($row[$studentIdColumn['index']] ?? '')) : '';

            if ($studentId === '') {
                $errors[] = "Row {$sheetRow}: Missing Student ID.";

                continue;
            }

            // Validate name
            if ($fullNameColumn) {
                $fullNameValue = trim((string) ($row[$fullNameColumn['index']] ?? ''));
                if ($fullNameValue === '') {
                    $errors[] = "Row {$sheetRow}: Missing Name.";
                }
            } else {
                $firstName = $firstNameColumn ? trim((string) ($row[$firstNameColumn['index']] ?? '')) : '';
                $lastName = $lastNameColumn ? trim((string) ($row[$lastNameColumn['index']] ?? '')) : '';

                if ($firstNameColumn && $firstName === '') {
                    $errors[] = "Row {$sheetRow}: Missing First Name.";
                }
                if ($lastNameColumn && $lastName === '') {
                    $errors[] = "Row {$sheetRow}: Missing Last Name.";
                }
            }

            $email = $emailColumn ? strtolower(trim((string) ($row[$emailColumn['index']] ?? ''))) : '';

            if ($email === '' && $emailColumn) {
                $errors[] = "Row {$sheetRow}: Missing Email.";
            } elseif ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Row {$sheetRow}: Invalid email '{$email}'.";
            }

            if (isset($seenStudentIds[$studentId])) {
                $firstSeenAt = $seenStudentIds[$studentId];
                $errors[] = "Row {$sheetRow}: Duplicate Student ID '{$studentId}' (already seen at row {$firstSeenAt}).";
            } else {
                $seenStudentIds[$studentId] = $sheetRow;
            }

            if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                if (isset($seenEmails[$email])) {
                    $firstSeenAt = $seenEmails[$email];
                    $errors[] = "Row {$sheetRow}: Duplicate email '{$email}' (already seen at row {$firstSeenAt}).";
                } else {
                    $seenEmails[$email] = $sheetRow;
                }
            }

            foreach ($caColumns as $column) {
                $value = $row[$column['index']] ?? null;

                if ($value === null || trim((string) $value) === '') {
                    continue;
                }

                if (! is_numeric($value)) {
                    $columnHeader = $column['header'] !== '' ? $column['header'] : "Column {$column['index']}";
                    $errors[] = "Row {$sheetRow}: Non-numeric CA score '{$value}' in {$columnHeader}.";
                }
            }

            if ($examColumn) {
                $examValue = $row[$examColumn['index']] ?? null;

                if ($examValue !== null && trim((string) $examValue) !== '' && ! is_numeric($examValue)) {
                    $errors[] = "Row {$sheetRow}: Non-numeric exam score '{$examValue}'.";
                }
            }
        }

        return [
            'valid' => count($errors) === 0,
            'errors' => $errors,
            'warnings' => $warnings,
        ];
    }

    /**
     * Auto-detect column roles from header row.
     *
     * @param  array<int, mixed>  $headers
     * @return array<int, array{index: int, header: string, detected_role: string, confirmed_role: string, assessment_name: string|null, max_score: float|null}>
     */
    public function parseHeaders(array $headers): array
    {
        $mappings = [];

        foreach ($headers as $index => $rawHeader) {
            $header = trim((string) $rawHeader);
            $lowerHeader = strtolower($header);

            $role = $this->detectRole($lowerHeader, $header);
            $assessmentName = null;
            $maxScore = null;

            if ($role === 'ca_assessment') {
                $parsed = $this->parseAssessmentHeader($header);
                $assessmentName = $parsed['name'];
                $maxScore = $parsed['max_score'];
            }

            if ($role === 'exam_score') {
                $maxScore = $this->parseExamDenominator($header);
            }

            $mappings[] = [
                'index' => $index,
                'header' => $header,
                'detected_role' => $role,
                'confirmed_role' => $role,
                'assessment_name' => $assessmentName,
                'max_score' => $maxScore,
            ];
        }

        return $mappings;
    }

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
            // Phase 1 — Assessment structure
            $caColumns = collect($columnMappings)->where('confirmed_role', 'ca_assessment');
            $examColumn = collect($columnMappings)->firstWhere('confirmed_role', 'exam_score');
            $assessmentMap = [];

            if ($caColumns->isNotEmpty()) {
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

                // Infer max_score from data for columns where header didn't provide it
                $columnMappings = $this->inferMaxScores($rows, $columnMappings);
                $caColumns = collect($columnMappings)->where('confirmed_role', 'ca_assessment');

                $sumMaxScores = $caColumns->sum('max_score') ?: $caColumns->count();
                $hasExplicitWeights = $caColumns->contains(fn ($c) => isset($c['ca_weight']));

                // Scale formula weights to sum to 100 (GradingService expects CA total on 0-100 scale)
                $weightSum = $hasExplicitWeights
                    ? $caColumns->filter(fn ($c) => isset($c['ca_weight']))->sum('ca_weight')
                    : 0;
                $weightScale = ($hasExplicitWeights && $weightSum > 0) ? 100 / $weightSum : 1;

                foreach ($caColumns as $sortIndex => $col) {
                    $maxScore = $col['max_score'] ?? 100;

                    if ($hasExplicitWeights && isset($col['ca_weight'])) {
                        // Scale formula-detected weight so CA total sums to 100
                        $normalizedTo = round((float) $col['ca_weight'] * $weightScale, 2);
                    } else {
                        // Fall back to proportional weight from max scores
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
            }

            // Phase 2 — Row processing
            $studentIdColumn = collect($columnMappings)->firstWhere('confirmed_role', 'student_id');
            $firstNameColumn = collect($columnMappings)->firstWhere('confirmed_role', 'first_name');
            $lastNameColumn = collect($columnMappings)->firstWhere('confirmed_role', 'last_name');
            $fullNameColumn = collect($columnMappings)->firstWhere('confirmed_role', 'full_name');
            $emailColumn = collect($columnMappings)->firstWhere('confirmed_role', 'email');
            $genderColumn = collect($columnMappings)->firstWhere('confirmed_role', 'gender');
            $programColumn = collect($columnMappings)->firstWhere('confirmed_role', 'program');
            $yearOfStudyColumn = collect($columnMappings)->firstWhere('confirmed_role', 'year_of_study');

            foreach ($rows as $rowIndex => $row) {
                $studentIdValue = $studentIdColumn ? trim((string) ($row[$studentIdColumn['index']] ?? '')) : '';

                if ($studentIdValue === '') {
                    $results['errors'][] = "Row {$rowIndex}: Missing student ID, skipped.";

                    continue;
                }

                if (strlen($studentIdValue) < 3) {
                    $results['errors'][] = "Row {$rowIndex}: Student ID '{$studentIdValue}' is unusually short.";
                }

                // Resolve name: full_name splits into lastName + firstName (UNZA "SURNAME FIRSTNAME" convention)
                if ($fullNameColumn) {
                    $fullName = trim((string) ($row[$fullNameColumn['index']] ?? ''));
                    $parts = preg_split('/\s+/', $fullName, 2);
                    $lastName = $parts[0] ?? '';
                    $firstName = $parts[1] ?? '';
                } else {
                    $firstName = $firstNameColumn ? trim((string) ($row[$firstNameColumn['index']] ?? '')) : '';
                    $lastName = $lastNameColumn ? trim((string) ($row[$lastNameColumn['index']] ?? '')) : '';
                }

                $email = $emailColumn ? trim((string) ($row[$emailColumn['index']] ?? '')) : '';
                $gender = $genderColumn ? trim((string) ($row[$genderColumn['index']] ?? '')) : null;
                $program = $programColumn ? trim((string) ($row[$programColumn['index']] ?? '')) : null;
                $yearOfStudy = $yearOfStudyColumn ? trim((string) ($row[$yearOfStudyColumn['index']] ?? '')) : null;

                // Apply defaults when no column value is available
                if (blank($program) && $defaultProgram !== null) {
                    $program = $defaultProgram;
                }
                if (blank($yearOfStudy) && $defaultYearOfStudy !== null) {
                    $yearOfStudy = (string) $defaultYearOfStudy;
                }

                $student = Student::where('student_id_number', $studentIdValue)->first();

                if ($student) {
                    $results['students_found']++;
                } else {
                    $resolvedEmail = $email ?: $studentIdValue.'@placeholder.unza.zm';
                    $existingByEmail = Student::where('email', $resolvedEmail)->first();

                    if ($existingByEmail) {
                        $results['errors'][] = "Row {$rowIndex}: Email '{$resolvedEmail}' already belongs to student {$existingByEmail->student_id_number}, skipped.";

                        continue;
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
                    $results['students_created']++;
                }

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

                // CA assessment scores
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

                // Exam score
                if ($examColumn) {
                    $examValue = $row[$examColumn['index']] ?? null;

                    if ($examValue !== null && trim((string) $examValue) !== '' && ! is_numeric($examValue)) {
                        $results['errors'][] = "Row {$rowIndex}: Non-numeric exam score '{$examValue}', skipped.";
                    } elseif ($examValue !== null && trim((string) $examValue) !== '') {
                        $examScore = (float) $examValue;
                        $examDenominator = $examColumn['max_score'] ?? 100;

                        if ($examDenominator > 0 && $examDenominator != 100) {
                            $examScore = round(($examScore / $examDenominator) * 100, 2);
                        }

                        $enrollment->update(['exam_score' => $examScore]);
                        $results['exam_scores_set']++;
                    }
                }
            }

            // Phase 3 — Grade resolution
            $gradingService = app(GradingService::class);
            $results['grades_resolved'] = $gradingService->resolveAllGrades($courseOffering);

            return $results;
        });
    }

    /**
     * Filter out trailing summary rows and non-data rows.
     * Removes rows where the student_id cell matches summary keywords,
     * and stops at 2+ consecutive blank rows (data block ended).
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
     * Detect the role of a column based on its header text.
     */
    protected function detectRole(string $lowerHeader, string $originalHeader): string
    {
        // Skip: formula artifacts leaked into headers (e.g. "=_xlfn.CONCAT(...)")
        if (str_starts_with(trim($originalHeader), '=')) {
            return 'skip';
        }

        // Skip: row numbering
        if (preg_match('/^(no\.?|#|s\/n|s\.?n\.?)$/i', $lowerHeader)) {
            return 'skip';
        }

        // Student ID
        if (preg_match('/student.?(id|number|no|num)|id.?number|comp.?no|computer.?number|matric|stud.?no/i', $lowerHeader)) {
            return 'student_id';
        }

        // Name columns
        if (preg_match('/^(first.?name|given.?name|fname|other.?names?)$/i', $lowerHeader)) {
            return 'first_name';
        }

        if (preg_match('/^(last.?name|surname|family.?name|lname)$/i', $lowerHeader)) {
            return 'last_name';
        }

        // Full name (single column containing surname + first name)
        if (preg_match('/^(full.?name|student.?name|name)$/i', $lowerHeader)) {
            return 'full_name';
        }

        // Email
        if (preg_match('/e-?mail/i', $lowerHeader)) {
            return 'email';
        }

        // Gender
        if (preg_match('/^(gender|sex)$/i', $lowerHeader)) {
            return 'gender';
        }

        // Program
        if (preg_match('/^(program|programme|prog|course.?of.?study)$/i', $lowerHeader)) {
            return 'program';
        }

        // Year of study
        if (preg_match('/^(year.?of.?study|study.?year|year|level)$/i', $lowerHeader)) {
            return 'year_of_study';
        }

        // Computed/aggregate columns to skip
        if (preg_match('/^(ca\s*[\/(]\s*\d+\)?|ca\s*grade|ca\s*total|course\s*total|course\s*grade|exam\s*grade|final\s*mark|final\s*grade|grade|gp|grade\s*point|total|remark|comment|note|class|result|check(\s*digit)?|def(erred)?|sup(plementary)?|rank|position|pass|fail|status|average|mean|cumulative|credits|points)$/i', $lowerHeader)) {
            return 'skip';
        }

        // Exam score
        if (preg_match('/^exam/i', $lowerHeader)) {
            return 'exam_score';
        }

        // Assessment with max score pattern: "Name (NN)" or "Name/NN"
        if (preg_match('/[\(\)\/]\s*\d+/i', $originalHeader)) {
            return 'ca_assessment';
        }

        // If the header is empty, skip
        if ($lowerHeader === '') {
            return 'skip';
        }

        // Fallback: treat as CA assessment
        return 'ca_assessment';
    }

    /**
     * Parse an assessment header like "Quiz 1 (30)" or "Assignment 1/20" to extract name and max score.
     *
     * @return array{name: string, max_score: float|null}
     */
    protected function parseAssessmentHeader(string $header): array
    {
        // Match "Name (NN)" pattern
        if (preg_match('/^(.+?)\s*\((\d+(?:\.\d+)?)\)\s*$/', $header, $matches)) {
            return [
                'name' => trim($matches[1]),
                'max_score' => (float) $matches[2],
            ];
        }

        // Match "Name/NN" pattern
        if (preg_match('/^(.+?)\s*\/\s*(\d+(?:\.\d+)?)\s*$/', $header, $matches)) {
            return [
                'name' => trim($matches[1]),
                'max_score' => (float) $matches[2],
            ];
        }

        return [
            'name' => trim($header),
            'max_score' => null,
        ];
    }

    /**
     * Infer max_score from data for CA columns where header didn't provide one.
     * Scans column values and rounds the max up to a standard denominator (10, 20, 25, 50, 100).
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, array{index: int, header: string, detected_role: string, confirmed_role: string, assessment_name: string|null, max_score: float|null}>  $columnMappings
     * @return array<int, array{index: int, header: string, detected_role: string, confirmed_role: string, assessment_name: string|null, max_score: float|null}>
     */
    protected function inferMaxScores(array $rows, array $columnMappings): array
    {
        $standardDenominators = [10, 20, 25, 50, 100];

        foreach ($columnMappings as &$mapping) {
            if ($mapping['confirmed_role'] !== 'ca_assessment' || $mapping['max_score'] !== null) {
                continue;
            }

            $maxValue = 0;

            foreach ($rows as $row) {
                $value = $row[$mapping['index']] ?? null;

                if ($value !== null && is_numeric($value)) {
                    $maxValue = max($maxValue, (float) $value);
                }
            }

            if ($maxValue <= 0) {
                $mapping['max_score'] = 100;

                continue;
            }

            // Round up to the nearest standard denominator
            $inferred = 100;
            foreach ($standardDenominators as $denom) {
                if ($maxValue <= $denom) {
                    $inferred = $denom;

                    break;
                }
            }

            $mapping['max_score'] = (float) $inferred;
        }
        unset($mapping);

        return $columnMappings;
    }

    /**
     * Parse exam header to extract denominator, e.g. "Exam/60" → 60, "Exam (100)" → 100.
     */
    protected function parseExamDenominator(string $header): ?float
    {
        if (preg_match('/[\(\/]\s*(\d+(?:\.\d+)?)\s*[\)]?\s*$/', $header, $matches)) {
            return (float) $matches[1];
        }

        return null;
    }

    /**
     * Detect which columns contain formulas in the first data row.
     * Used to auto-skip computed/derived columns during mapping.
     *
     * @return array<int, int> Column indices that contain formulas
     */
    public function detectFormulaColumns(string $filePath, string $worksheetName, int $columnCount): array
    {
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(false);

            if (method_exists($reader, 'setLoadSheetsOnly')) {
                $reader->setLoadSheetsOnly([$worksheetName]);
            }

            $reader->setReadFilter(new class implements \PhpOffice\PhpSpreadsheet\Reader\IReadFilter
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
                $coordinate = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col + 1).'2';
                $cellValue = $sheet->getCell($coordinate)->getValue();

                if (is_string($cellValue) && str_starts_with($cellValue, '=')) {
                    $formulaColumns[] = $col;
                }
            }

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            return $formulaColumns;
        } catch (\Throwable) {
            return [];
        }
    }

    /**
     * Extract assessment weights from a CA column formula.
     * Parses patterns like "=(F2*0.025)+(G2*0.025)" and returns column-letter → weight mapping.
     *
     * @return array<string, float>|null Column letter → weight, or null if no formula found
     */
    public function extractWeightsFromFormula(string $filePath, string $worksheetName, int $caColumnIndex): ?array
    {
        try {
            $reader = \PhpOffice\PhpSpreadsheet\IOFactory::createReaderForFile($filePath);
            $reader->setReadDataOnly(false);

            if (method_exists($reader, 'setLoadSheetsOnly')) {
                $reader->setLoadSheetsOnly([$worksheetName]);
            }

            $spreadsheet = $reader->load($filePath);
            $sheet = $spreadsheet->getSheetByName($worksheetName) ?? $spreadsheet->getSheet(0);

            $colLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($caColumnIndex + 1);
            $formula = $sheet->getCell("{$colLetter}2")->getValue();

            $spreadsheet->disconnectWorksheets();
            unset($spreadsheet);

            if (! is_string($formula) || ! str_starts_with($formula, '=')) {
                return null;
            }

            // Parse formula like =(F2*0.025)+(G2*0.025)
            $weights = [];
            if (preg_match_all('/([A-Z]+)\d+\s*\*\s*([\d.]+)/', $formula, $matches, PREG_SET_ORDER)) {
                foreach ($matches as $match) {
                    $weights[$match[1]] = (float) $match[2];
                }
            }

            return ! empty($weights) ? $weights : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Auto-select the best worksheet from a list of sheet names.
     * Returns the recommended sheet name or null if no preference.
     *
     * @param  array<int, string>  $sheetNames
     */
    public function autoSelectSheet(array $sheetNames, ?string $courseCode = null): ?string
    {
        if (count($sheetNames) <= 1) {
            return $sheetNames[0] ?? null;
        }

        $reportPatterns = [
            '/grade\s*summary/i',
            '/gender\s*analysis/i',
            '/chart/i',
            '/summary/i',
            '/report/i',
            '/statistics/i',
            '/analysis/i',
        ];

        $nonReportSheets = [];

        foreach ($sheetNames as $name) {
            $isReport = false;
            foreach ($reportPatterns as $pattern) {
                if (preg_match($pattern, $name)) {
                    $isReport = true;

                    break;
                }
            }

            if (! $isReport) {
                $nonReportSheets[] = $name;
            }
        }

        // Look for sheet named "Data"
        foreach ($nonReportSheets as $name) {
            if (strcasecmp($name, 'Data') === 0) {
                return $name;
            }
        }

        // Look for sheet matching course code
        if ($courseCode) {
            foreach ($nonReportSheets as $name) {
                if (stripos($name, $courseCode) !== false) {
                    return $name;
                }
            }
        }

        // Return the first non-report sheet
        return $nonReportSheets[0] ?? $sheetNames[0];
    }

    /**
     * Identify which sheets appear to be report/summary sheets.
     *
     * @param  array<int, string>  $sheetNames
     * @return array<string, bool> Sheet name → is report sheet
     */
    public function flagReportSheets(array $sheetNames): array
    {
        $reportPatterns = [
            '/grade\s*summary/i',
            '/gender\s*analysis/i',
            '/chart/i',
            '/summary/i',
            '/report/i',
            '/statistics/i',
            '/analysis/i',
        ];

        $flags = [];

        foreach ($sheetNames as $name) {
            $isReport = false;
            foreach ($reportPatterns as $pattern) {
                if (preg_match($pattern, $name)) {
                    $isReport = true;

                    break;
                }
            }
            $flags[$name] = $isReport;
        }

        return $flags;
    }

    /**
     * Extended preflight validation with additional checks.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, array{index: int, header: string, detected_role: string, confirmed_role: string, assessment_name: string|null, max_score: float|null}>  $columnMappings
     * @return array{valid: bool, errors: array<int, string>, warnings: array<int, string>, info: array<int, string>}
     */
    public function extendedPreflight(array $rows, array $columnMappings): array
    {
        $base = $this->preflight($rows, $columnMappings);
        $errors = $base['errors'];
        $warnings = $base['warnings'];
        $info = [];

        $studentIdColumn = collect($columnMappings)->firstWhere('confirmed_role', 'student_id');
        $examColumn = collect($columnMappings)->firstWhere('confirmed_role', 'exam_score');
        $caColumns = collect($columnMappings)->where('confirmed_role', 'ca_assessment');

        // V-04: Score range validation
        foreach ($rows as $rowIndex => $row) {
            $sheetRow = $rowIndex + 2;
            $studentId = $studentIdColumn ? trim((string) ($row[$studentIdColumn['index']] ?? '')) : '';

            if ($studentId === '') {
                continue;
            }

            foreach ($caColumns as $column) {
                $value = $row[$column['index']] ?? null;

                if ($value === null || trim((string) $value) === '' || ! is_numeric($value)) {
                    continue;
                }

                $score = (float) $value;
                $maxScore = $column['max_score'] ?? null;

                if ($score < 0) {
                    $errors[] = "Row {$sheetRow}: Negative score {$score} in {$column['header']}.";
                } elseif ($maxScore !== null && $score > $maxScore) {
                    $warnings[] = "Row {$sheetRow}: Score {$score} exceeds max {$maxScore} in {$column['header']}.";
                }
            }

            if ($examColumn) {
                $examValue = $row[$examColumn['index']] ?? null;

                if ($examValue !== null && trim((string) $examValue) !== '' && is_numeric($examValue)) {
                    $examScore = (float) $examValue;
                    $examMax = $examColumn['max_score'] ?? 100;

                    if ($examScore < 0) {
                        $errors[] = "Row {$sheetRow}: Negative exam score {$examScore}.";
                    } elseif ($examMax !== null && $examScore > $examMax) {
                        $warnings[] = "Row {$sheetRow}: Exam score {$examScore} exceeds max {$examMax}.";
                    }
                }
            }
        }

        // V-06: Weight sum validation
        if ($caColumns->isNotEmpty()) {
            $totalMaxScore = $caColumns->sum('max_score');
            if ($totalMaxScore !== null && $totalMaxScore > 0) {
                // Weights should sum reasonably — just check they're all present
                $missingWeights = $caColumns->filter(fn ($c) => $c['max_score'] === null)->count();
                if ($missingWeights > 0) {
                    $warnings[] = "{$missingWeights} CA column(s) have no max score detected. Weights may be inaccurate.";
                }
            }
        }

        // V-08: NE detection for blank exam cells
        if ($examColumn) {
            $blankExamCount = 0;
            foreach ($rows as $row) {
                $studentId = $studentIdColumn ? trim((string) ($row[$studentIdColumn['index']] ?? '')) : '';

                if ($studentId === '') {
                    continue;
                }

                $examValue = $row[$examColumn['index']] ?? null;

                if ($examValue === null || trim((string) $examValue) === '') {
                    $blankExamCount++;
                }
            }

            if ($blankExamCount > 0) {
                $warnings[] = "{$blankExamCount} student(s) have blank exam cells. These may be NE (Not Examined).";
            }
        }

        // V-11: Embedded report row detection (non-numeric student IDs)
        $embeddedReportRows = 0;
        foreach ($rows as $rowIndex => $row) {
            $sheetRow = $rowIndex + 2;
            $studentId = $studentIdColumn ? trim((string) ($row[$studentIdColumn['index']] ?? '')) : '';

            if ($studentId !== '' && ! preg_match('/^\d/', $studentId)) {
                $embeddedReportRows++;
                if ($embeddedReportRows <= 3) {
                    $warnings[] = "Row {$sheetRow}: Student ID '{$studentId}' doesn't start with a digit — may be an embedded report row.";
                }
            }
        }

        if ($embeddedReportRows > 3) {
            $warnings[] = ($embeddedReportRows - 3).' additional rows with non-numeric student IDs detected.';
        }

        // V-12: Non-data row filtering (all-blank scores)
        $allBlankScoreRows = 0;
        foreach ($rows as $rowIndex => $row) {
            $studentId = $studentIdColumn ? trim((string) ($row[$studentIdColumn['index']] ?? '')) : '';

            if ($studentId === '') {
                continue;
            }

            $hasAnyScore = false;

            foreach ($caColumns as $column) {
                $value = $row[$column['index']] ?? null;

                if ($value !== null && trim((string) $value) !== '') {
                    $hasAnyScore = true;

                    break;
                }
            }

            if (! $hasAnyScore && $examColumn) {
                $examValue = $row[$examColumn['index']] ?? null;

                if ($examValue !== null && trim((string) $examValue) !== '') {
                    $hasAnyScore = true;
                }
            }

            if (! $hasAnyScore) {
                $allBlankScoreRows++;
            }
        }

        if ($allBlankScoreRows > 0) {
            $info[] = "{$allBlankScoreRows} student(s) have all blank scores — they will be enrolled without grades.";
        }

        // V-13: Unmatched students
        $allStudentIds = collect($rows)
            ->map(fn ($row) => $studentIdColumn ? trim((string) ($row[$studentIdColumn['index']] ?? '')) : '')
            ->filter(fn ($id) => $id !== '')
            ->unique()
            ->values();

        $existingStudentIds = Student::whereIn('student_id_number', $allStudentIds)
            ->pluck('student_id_number')
            ->flip();

        $unmatchedCount = $allStudentIds->reject(fn ($id) => $existingStudentIds->has($id))->count();

        if ($unmatchedCount > 0) {
            $info[] = "{$unmatchedCount} student(s) are new and will be created during import.";
        }

        // V-14: Missing score cells
        $missingScoreCells = 0;
        foreach ($rows as $row) {
            $studentId = $studentIdColumn ? trim((string) ($row[$studentIdColumn['index']] ?? '')) : '';

            if ($studentId === '') {
                continue;
            }

            foreach ($caColumns as $column) {
                $value = $row[$column['index']] ?? null;

                if ($value === null || trim((string) $value) === '') {
                    $missingScoreCells++;
                }
            }
        }

        if ($missingScoreCells > 0) {
            $info[] = "{$missingScoreCells} individual CA score cell(s) are blank and will be skipped.";
        }

        return [
            'valid' => count($errors) === 0,
            'errors' => $errors,
            'warnings' => $warnings,
            'info' => $info,
        ];
    }
}
