<?php

namespace App\Imports;

use App\Models\Assessment;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeResult;
use App\Models\Student;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class GradesImport implements ToModel, WithHeadingRow
{
    protected int $importedCount = 0;

    protected int $skippedCount = 0;

    public function __construct(
        protected CourseOffering $courseOffering,
    ) {}

    /**
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function model(array $row): ?GradeResult
    {
        $studentIdNumber = $row['student_id'] ?? $row['student_id_number'] ?? null;
        $assessmentName = $row['assessment_name'] ?? $row['assessment'] ?? null;
        $rawScore = $row['raw_score'] ?? $row['score'] ?? null;

        if (! $studentIdNumber || ! $assessmentName || $rawScore === null) {
            $this->skippedCount++;

            return null;
        }

        $student = Student::where('student_id_number', $studentIdNumber)->first();
        if (! $student) {
            $this->skippedCount++;

            return null;
        }

        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('course_offering_id', $this->courseOffering->id)
            ->first();

        if (! $enrollment) {
            $this->skippedCount++;

            return null;
        }

        $assessment = Assessment::where('name', $assessmentName)
            ->whereHas('assessmentGroup', function ($query) {
                $query->where('course_offering_id', $this->courseOffering->id);
            })
            ->first();

        if (! $assessment) {
            $this->skippedCount++;

            return null;
        }

        $result = GradeResult::updateOrCreate(
            [
                'enrollment_id' => $enrollment->id,
                'assessment_id' => $assessment->id,
            ],
            [
                'raw_score' => (float) $rawScore,
                'source' => 'csv_import',
            ]
        );

        $normalized = $result->calculateNormalizedScore();
        if ($normalized !== null) {
            $result->update(['normalized_score' => $normalized]);
        }

        $this->importedCount++;

        return null;
    }

    public function getImportedCount(): int
    {
        return $this->importedCount;
    }

    public function getSkippedCount(): int
    {
        return $this->skippedCount;
    }
}
