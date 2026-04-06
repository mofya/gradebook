<?php

namespace App\Services\Import;

use App\Models\Student;

class CourseDataValidator
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

        $studentIdCount = $roles->filter(fn ($r) => $r === 'student_id')->count();
        if ($studentIdCount === 0) {
            $errors[] = 'No Student ID column detected. Exactly one column must be mapped to Student ID.';
        } elseif ($studentIdCount > 1) {
            $errors[] = 'Multiple Student ID columns detected. Exactly one column should be mapped to Student ID.';
        }

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

        $emailCount = $roles->filter(fn ($r) => $r === 'email')->count();
        if ($emailCount === 0) {
            $errors[] = 'No Email column detected. Exactly one column must be mapped to Email.';
        } elseif ($emailCount > 1) {
            $errors[] = 'Multiple Email columns detected. Exactly one column should be mapped to Email.';
        }

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

        if ($caColumns->isNotEmpty()) {
            $missingWeights = $caColumns->filter(fn ($c) => $c['max_score'] === null)->count();
            if ($missingWeights > 0) {
                $warnings[] = "{$missingWeights} CA column(s) have no max score detected. Weights may be inaccurate.";
            }
        }

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

        $allBlankScoreRows = 0;
        foreach ($rows as $row) {
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

    /**
     * Infer max_score from data for CA columns where header didn't provide one.
     *
     * @param  array<int, array<int, mixed>>  $rows
     * @param  array<int, array{index: int, header: string, detected_role: string, confirmed_role: string, assessment_name: string|null, max_score: float|null}>  $columnMappings
     * @return array<int, array{index: int, header: string, detected_role: string, confirmed_role: string, assessment_name: string|null, max_score: float|null}>
     */
    public function inferMaxScores(array $rows, array $columnMappings): array
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
}
