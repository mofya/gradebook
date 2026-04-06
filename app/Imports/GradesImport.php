<?php

namespace App\Imports;

use App\Models\Assessment;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeResult;
use App\Models\Student;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

class GradesImport implements ToModel, WithHeadingRow
{
    protected int $importedCount = 0;

    protected int $skippedCount = 0;

    /** @var array<int, string> */
    protected array $skippedDetails = [];

    public function __construct(
        protected CourseOffering $courseOffering,
    ) {}

    /**
     * @return Model|null
     */
    public function model(array $row): ?GradeResult
    {
        $studentIdNumber = $row['student_id'] ?? $row['student_id_number'] ?? null;
        $assessmentName = $row['assessment_name'] ?? $row['assessment'] ?? null;
        $rawScore = $row['raw_score'] ?? $row['score'] ?? null;

        if (! $studentIdNumber || ! $assessmentName || $rawScore === null) {
            $this->skippedCount++;
            $this->skippedDetails[] = 'Row missing required fields (student_id, assessment_name, or score).';

            return null;
        }

        $student = Student::where('student_id_number', $studentIdNumber)->first();
        if (! $student) {
            $this->skippedCount++;
            $this->skippedDetails[] = "Student ID '{$studentIdNumber}' not found.";

            return null;
        }

        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('course_offering_id', $this->courseOffering->id)
            ->first();

        if (! $enrollment) {
            $this->skippedCount++;
            $this->skippedDetails[] = "Student '{$studentIdNumber}' not enrolled in this offering.";

            return null;
        }

        $assessment = Assessment::where('name', $assessmentName)
            ->whereHas('assessmentGroup', function ($query) {
                $query->where('course_offering_id', $this->courseOffering->id);
            })
            ->first();

        if (! $assessment) {
            $this->skippedCount++;
            $this->skippedDetails[] = "Assessment '{$assessmentName}' not found in this offering.";

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

    /**
     * @return array<int, string>
     */
    public function getSkippedDetails(): array
    {
        return $this->skippedDetails;
    }
}
