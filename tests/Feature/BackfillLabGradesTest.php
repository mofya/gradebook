<?php

namespace Tests\Feature;

use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeResult;
use App\Models\Semester;
use App\Models\Student;
use App\Models\UnmatchedLabGrade;
use App\Models\Year;
use App\Services\BackfillLabGradesService;
use App\Services\LabGradeImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BackfillLabGradesTest extends TestCase
{
    use RefreshDatabase;

    private function createOfferingWithAssessment(): array
    {
        $year = Year::factory()->create();
        $semester = Semester::factory()->create(['year_id' => $year->id]);
        $course = Course::factory()->create(['year_id' => $year->id]);

        $offering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
        ]);

        $group = AssessmentGroup::factory()->create([
            'course_offering_id' => $offering->id,
            'type' => 'ca',
        ]);

        $assessment = Assessment::factory()->create([
            'assessment_group_id' => $group->id,
            'course_id' => $course->id,
            'max_raw_score' => 100,
            'normalized_to' => 100,
            'weight' => 100,
            'has_subsections' => true,
        ]);

        return [$offering, $assessment];
    }

    public function test_backfill_creates_grade_results_for_matching_student(): void
    {
        [$offering, $assessment] = $this->createOfferingWithAssessment();

        $student = Student::factory()->create(['github_username' => 'testuser123']);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
        ]);

        UnmatchedLabGrade::create([
            'course_offering_id' => $offering->id,
            'assessment_id' => $assessment->id,
            'github_username' => 'testuser123',
            'row_data' => [
                'GitHub Username' => 'testuser123',
                'Final Score (%)' => '85.5',
                'Letter Grade' => 'A',
                'Visible Tests (%)' => '90.0',
                'Hidden Tests (%)' => '80.0',
                'Code Quality (%)' => '95.0',
            ],
            'status' => 'pending',
        ]);

        $service = app(BackfillLabGradesService::class);
        $result = $service->backfillForStudent($student);

        $this->assertEquals(1, $result['grades_created']);
        $this->assertEquals(1, $result['offerings_recalculated']);

        // Grade result should exist
        $gradeResult = GradeResult::where('enrollment_id', $enrollment->id)
            ->where('assessment_id', $assessment->id)
            ->first();

        $this->assertNotNull($gradeResult);
        $this->assertEquals(85.5, (float) $gradeResult->raw_score);

        // Unmatched row should be marked as matched
        $unmatched = UnmatchedLabGrade::first();
        $this->assertEquals('matched', $unmatched->status);
        $this->assertEquals($student->id, $unmatched->matched_student_id);
        $this->assertNotNull($unmatched->matched_at);
    }

    public function test_backfill_skips_when_student_not_enrolled(): void
    {
        [$offering, $assessment] = $this->createOfferingWithAssessment();

        $student = Student::factory()->create(['github_username' => 'notenrolled']);

        UnmatchedLabGrade::create([
            'course_offering_id' => $offering->id,
            'assessment_id' => $assessment->id,
            'github_username' => 'notenrolled',
            'row_data' => ['GitHub Username' => 'notenrolled', 'Final Score (%)' => '90'],
            'status' => 'pending',
        ]);

        $service = app(BackfillLabGradesService::class);
        $result = $service->backfillForStudent($student);

        $this->assertEquals(0, $result['grades_created']);

        // Row should remain pending
        $this->assertEquals('pending', UnmatchedLabGrade::first()->status);
    }

    public function test_backfill_ignores_already_matched_rows(): void
    {
        [$offering, $assessment] = $this->createOfferingWithAssessment();

        $student = Student::factory()->create(['github_username' => 'alreadymatched']);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
        ]);

        UnmatchedLabGrade::create([
            'course_offering_id' => $offering->id,
            'assessment_id' => $assessment->id,
            'github_username' => 'alreadymatched',
            'row_data' => ['GitHub Username' => 'alreadymatched', 'Final Score (%)' => '90'],
            'status' => 'matched',
            'matched_at' => now(),
            'matched_student_id' => $student->id,
        ]);

        $service = app(BackfillLabGradesService::class);
        $result = $service->backfillForStudent($student);

        $this->assertEquals(0, $result['grades_created']);
    }

    public function test_backfill_matches_case_insensitively(): void
    {
        [$offering, $assessment] = $this->createOfferingWithAssessment();

        $student = Student::factory()->create(['github_username' => 'MixedCase']);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
        ]);

        UnmatchedLabGrade::create([
            'course_offering_id' => $offering->id,
            'assessment_id' => $assessment->id,
            'github_username' => 'mixedcase',
            'row_data' => ['GitHub Username' => 'MixedCase', 'Final Score (%)' => '75'],
            'status' => 'pending',
        ]);

        $service = app(BackfillLabGradesService::class);
        $result = $service->backfillForStudent($student);

        $this->assertEquals(1, $result['grades_created']);
    }

    public function test_backfill_handles_multiple_assessments(): void
    {
        [$offering, $assessment1] = $this->createOfferingWithAssessment();

        $assessment2 = Assessment::factory()->create([
            'assessment_group_id' => $assessment1->assessment_group_id,
            'course_id' => $offering->course_id,
            'max_raw_score' => 100,
            'normalized_to' => 100,
            'weight' => 100,
        ]);

        $student = Student::factory()->create(['github_username' => 'multiuser']);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
        ]);

        UnmatchedLabGrade::create([
            'course_offering_id' => $offering->id,
            'assessment_id' => $assessment1->id,
            'github_username' => 'multiuser',
            'row_data' => ['GitHub Username' => 'multiuser', 'Final Score (%)' => '80'],
            'status' => 'pending',
        ]);

        UnmatchedLabGrade::create([
            'course_offering_id' => $offering->id,
            'assessment_id' => $assessment2->id,
            'github_username' => 'multiuser',
            'row_data' => ['GitHub Username' => 'multiuser', 'Final Score (%)' => '90'],
            'status' => 'pending',
        ]);

        $service = app(BackfillLabGradesService::class);
        $result = $service->backfillForStudent($student);

        $this->assertEquals(2, $result['grades_created']);
        $this->assertEquals(1, $result['offerings_recalculated']);
        $this->assertEquals(2, UnmatchedLabGrade::where('status', 'matched')->count());
    }

    public function test_backfill_returns_zero_when_no_github_username(): void
    {
        $student = Student::factory()->create(['github_username' => null]);

        $service = app(BackfillLabGradesService::class);
        $result = $service->backfillForStudent($student);

        $this->assertEquals(0, $result['grades_created']);
        $this->assertEquals(0, $result['offerings_recalculated']);
    }

    public function test_import_stores_unmatched_rows(): void
    {
        [$offering, $assessment] = $this->createOfferingWithAssessment();

        $student = Student::factory()->create(['github_username' => 'enrolled-user']);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
        ]);

        $rows = [
            ['GitHub Username' => 'enrolled-user', 'Final Score (%)' => '80'],
            ['GitHub Username' => 'unknown-user', 'Final Score (%)' => '90'],
        ];

        $importService = app(LabGradeImportService::class);
        $stats = $importService->import($offering, $assessment, $rows);

        $this->assertEquals(1, $stats['grades_imported']);
        $this->assertEquals(1, $stats['skipped']);

        // Unmatched row should be stored
        $unmatched = UnmatchedLabGrade::where('github_username', 'unknown-user')->first();
        $this->assertNotNull($unmatched);
        $this->assertEquals('pending', $unmatched->status);
        $this->assertEquals($offering->id, $unmatched->course_offering_id);
        $this->assertEquals($assessment->id, $unmatched->assessment_id);
    }
}
