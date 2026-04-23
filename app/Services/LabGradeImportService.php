<?php

namespace App\Services;

use App\Models\Assessment;
use App\Models\AssessmentSubsection;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeResult;
use App\Models\Student;
use App\Models\SubsectionScore;
use App\Models\UnmatchedLabGrade;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LabGradeImportService
{
    /**
     * Columns from the CSV that become subsections with their max scores.
     *
     * @var array<string, float>
     */
    protected const SUBSECTION_COLUMNS = [
        'Visible Tests (%)' => 100,
        'Hidden Tests (%)' => 100,
        'Code Quality (%)' => 100,
    ];

    /**
     * Parse CSV file and return headers + rows.
     *
     * @return array{headers: array<int, string>, rows: array<int, array<string, string>>}
     */
    public function parseCsv(string $filePath): array
    {
        $handle = fopen($filePath, 'r');
        $headers = fgetcsv($handle);
        $rows = [];

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) === count($headers)) {
                $rows[] = array_combine($headers, $row);
            }
        }

        fclose($handle);

        return ['headers' => $headers, 'rows' => $rows];
    }

    /**
     * Preview the import: match GitHub usernames to enrolled students.
     *
     * @param  array<int, array<string, string>>  $rows
     * @return array{matched: array<int, array{github_username: string, student: Student, row: array<string, string>}>, unmatched: array<int, array{github_username: string, row: array<string, string>}>, total: int}
     */
    public function preview(array $rows, CourseOffering $courseOffering): array
    {
        $enrolledStudents = $courseOffering->enrollments()
            ->with('student')
            ->get()
            ->pluck('student')
            ->filter()
            ->keyBy(fn (Student $s) => strtolower($s->github_username ?? ''));

        $matched = [];
        $unmatched = [];

        foreach ($rows as $row) {
            $githubUsername = trim($row['GitHub Username'] ?? '');
            if (blank($githubUsername)) {
                continue;
            }

            $student = $enrolledStudents->get(strtolower($githubUsername));

            if ($student) {
                $matched[] = [
                    'github_username' => $githubUsername,
                    'student' => $student,
                    'row' => $row,
                ];
            } else {
                $unmatched[] = [
                    'github_username' => $githubUsername,
                    'row' => $row,
                ];
            }
        }

        return [
            'matched' => $matched,
            'unmatched' => $unmatched,
            'total' => count($rows),
        ];
    }

    /**
     * Import lab grades into the gradebook.
     *
     * @param  array<int, array<string, string>>  $rows
     * @param  array<string, int>  $manualMappings  GitHub username => student_id for unmatched students
     * @return array{grades_imported: int, subsections_created: int, skipped: int, errors: array<int, string>}
     */
    public function import(
        CourseOffering $courseOffering,
        Assessment $assessment,
        array $rows,
        array $manualMappings = [],
    ): array {
        $stats = [
            'grades_imported' => 0,
            'subsections_created' => 0,
            'skipped' => 0,
            'errors' => [],
        ];

        return DB::transaction(function () use ($courseOffering, $assessment, $rows, $manualMappings, &$stats) {
            // Ensure assessment has subsections enabled
            if (! $assessment->has_subsections) {
                $assessment->update(['has_subsections' => true]);
            }

            // Create or find subsections
            $subsections = $this->ensureSubsections($assessment);
            $stats['subsections_created'] = $subsections->filter(fn ($s) => $s->wasRecentlyCreated)->count();

            // Build student lookup: github_username (lowercase) => enrollment
            $enrollmentsWithStudent = $courseOffering->enrollments()
                ->with('student')
                ->get();

            $enrollments = $enrollmentsWithStudent->keyBy(
                fn (Enrollment $e) => strtolower($e->student->github_username ?? '')
            );

            // Student ID number (e.g. "2023000645") lookup
            $enrollmentsByStudentIdNumber = $enrollmentsWithStudent->keyBy(
                fn (Enrollment $e) => (string) ($e->student->student_id_number ?? '')
            );

            // Also build manual mapping lookup: student_id => enrollment (PK, for manualMappings)
            $enrollmentsByStudentId = $courseOffering->enrollments()
                ->get()
                ->keyBy('student_id');

            foreach ($rows as $index => $row) {
                $githubUsername = trim($row['GitHub Username'] ?? '');
                $studentIdNumber = trim($row['Student ID'] ?? '');

                if (blank($githubUsername) && blank($studentIdNumber)) {
                    $stats['skipped']++;

                    continue;
                }

                // Prefer student_id_number match (exact, stable) then github_username.
                $enrollment = null;
                if (filled($studentIdNumber)) {
                    $enrollment = $enrollmentsByStudentIdNumber->get($studentIdNumber);
                }
                if (! $enrollment && filled($githubUsername)) {
                    $enrollment = $enrollments->get(strtolower($githubUsername));
                }

                if (! $enrollment && filled($githubUsername) && isset($manualMappings[$githubUsername])) {
                    $studentId = $manualMappings[$githubUsername];
                    $enrollment = $enrollmentsByStudentId->get($studentId);

                    // Also update the student's github_username for future imports
                    if ($enrollment) {
                        $enrollment->student->update(['github_username' => $githubUsername]);
                    }
                }

                if (! $enrollment) {
                    // Store unmatched row for future auto-matching. Use the
                    // github_username as key if available; otherwise derive
                    // a synthesized key from the student_id_number.
                    $unmatchedKey = filled($githubUsername)
                        ? strtolower($githubUsername)
                        : 'sid:'.$studentIdNumber;

                    UnmatchedLabGrade::updateOrCreate(
                        [
                            'course_offering_id' => $courseOffering->id,
                            'assessment_id' => $assessment->id,
                            'github_username' => $unmatchedKey,
                        ],
                        [
                            'row_data' => $row,
                            'status' => 'pending',
                            'matched_at' => null,
                            'matched_student_id' => null,
                        ]
                    );

                    $stats['skipped']++;

                    continue;
                }

                $this->importRow($enrollment, $assessment, $row, $subsections);
                $stats['grades_imported']++;
            }

            // Resolve grades for the offering
            app(GradingService::class)->resolveAllGrades($courseOffering);

            return $stats;
        });
    }

    /**
     * Import a single CSV row as a grade result for the given enrollment and assessment.
     *
     * @param  array<string, string>  $row
     * @param  Collection<string, AssessmentSubsection>|null  $subsections
     */
    public function importRow(
        Enrollment $enrollment,
        Assessment $assessment,
        array $row,
        ?Collection $subsections = null,
    ): GradeResult {
        $subsections ??= $this->ensureSubsections($assessment);

        $finalScore = (float) ($row['Final Score (%)'] ?? 0);

        // Create or update grade result
        $gradeResult = GradeResult::updateOrCreate(
            [
                'enrollment_id' => $enrollment->id,
                'assessment_id' => $assessment->id,
            ],
            [
                'raw_score' => $finalScore,
                'source' => 'lab_import',
                'student_feedback' => $this->buildStudentFeedback($row),
                'notes' => $row['Instructor Notes'] ?? null,
            ]
        );

        // Calculate and store the properly normalized score
        $normalized = $gradeResult->calculateNormalizedScore();
        if ($normalized !== null) {
            $gradeResult->updateQuietly(['normalized_score' => $normalized]);
        }

        // Create subsection scores
        foreach (static::SUBSECTION_COLUMNS as $columnName => $maxScore) {
            $subsection = $subsections->get($columnName);
            if (! $subsection) {
                continue;
            }

            $score = (float) ($row[$columnName] ?? 0);

            SubsectionScore::updateOrCreate(
                [
                    'grade_result_id' => $gradeResult->id,
                    'assessment_subsection_id' => $subsection->id,
                ],
                [
                    'score' => $score,
                ]
            );
        }

        return $gradeResult;
    }

    /**
     * Ensure assessment subsections exist for the standard lab columns.
     *
     * @return Collection<string, AssessmentSubsection>
     */
    protected function ensureSubsections(Assessment $assessment): Collection
    {
        $subsections = collect();
        $sortOrder = 1;

        foreach (static::SUBSECTION_COLUMNS as $name => $maxScore) {
            $subsection = AssessmentSubsection::firstOrCreate(
                [
                    'assessment_id' => $assessment->id,
                    'name' => $name,
                ],
                [
                    'max_score' => $maxScore,
                    'sort_order' => $sortOrder,
                ]
            );

            $subsections->put($name, $subsection);
            $sortOrder++;
        }

        return $subsections;
    }

    /**
     * Build the student-visible feedback text from a CSV row.
     *
     * @param  array<string, string>  $row
     */
    protected function buildStudentFeedback(array $row): ?string
    {
        $parts = [];

        $feedback = trim($row['Student Feedback'] ?? '');
        if (filled($feedback)) {
            $parts[] = $feedback;
        }

        $plagiarismNote = trim($row['Plagiarism Note'] ?? '');
        if (filled($plagiarismNote)) {
            $parts[] = $plagiarismNote;
        }

        return filled($parts) ? implode("\n\n", $parts) : null;
    }

    /**
     * Get the list of subsection column names this service expects.
     *
     * @return array<string, float>
     */
    public function getSubsectionColumns(): array
    {
        return static::SUBSECTION_COLUMNS;
    }
}
