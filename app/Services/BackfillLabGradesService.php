<?php

namespace App\Services;

use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\UnmatchedLabGrade;

class BackfillLabGradesService
{
    public function __construct(
        private LabGradeImportService $importService,
        private GradingService $gradingService,
    ) {}

    /**
     * Backfill any pending unmatched lab grades that match the student's GitHub username.
     *
     * @return array{grades_created: int, offerings_recalculated: int}
     */
    public function backfillForStudent(Student $student): array
    {
        $username = $student->github_username;

        if (blank($username)) {
            return ['grades_created' => 0, 'offerings_recalculated' => 0];
        }

        $pendingRows = UnmatchedLabGrade::query()
            ->where('github_username', strtolower($username))
            ->where('status', 'pending')
            ->with(['assessment', 'courseOffering'])
            ->get();

        if ($pendingRows->isEmpty()) {
            return ['grades_created' => 0, 'offerings_recalculated' => 0];
        }

        $gradesCreated = 0;
        $affectedOfferingIds = [];

        foreach ($pendingRows as $pendingRow) {
            // Find enrollment for this student in the course offering
            $enrollment = Enrollment::query()
                ->where('student_id', $student->id)
                ->where('course_offering_id', $pendingRow->course_offering_id)
                ->first();

            if (! $enrollment) {
                continue;
            }

            $this->importService->importRow(
                $enrollment,
                $pendingRow->assessment,
                $pendingRow->row_data,
            );

            $pendingRow->update([
                'status' => 'matched',
                'matched_at' => now(),
                'matched_student_id' => $student->id,
            ]);

            $gradesCreated++;
            $affectedOfferingIds[$pendingRow->course_offering_id] = true;
        }

        // Recalculate grades for all affected course offerings
        foreach (array_keys($affectedOfferingIds) as $offeringId) {
            $offering = CourseOffering::find($offeringId);
            if ($offering) {
                $this->gradingService->resolveAllGrades($offering);
            }
        }

        return [
            'grades_created' => $gradesCreated,
            'offerings_recalculated' => count($affectedOfferingIds),
        ];
    }
}
