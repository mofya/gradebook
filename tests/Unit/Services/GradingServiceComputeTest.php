<?php

namespace Tests\Unit\Services;

use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeResult;
use App\Models\GradingScheme;
use App\Models\GradingSchemeLevel;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Year;
use App\Services\GradingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradingServiceComputeTest extends TestCase
{
    use RefreshDatabase;

    private GradingService $gradingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gradingService = new GradingService;
    }

    private function createOfferingWithGroups(
        float $caWeight = 50,
        float $examWeight = 50,
        ?GradingScheme $scheme = null,
    ): CourseOffering {
        $year = Year::factory()->create();
        $semester = Semester::factory()->create(['year_id' => $year->id]);
        $course = Course::factory()->create(['year_id' => $year->id, 'credits' => 3]);

        return CourseOffering::factory()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'ca_weight' => $caWeight,
            'exam_weight' => $examWeight,
            'grading_scheme_id' => $scheme?->id,
        ]);
    }

    private function createEnrollment(CourseOffering $offering): Enrollment
    {
        $student = Student::factory()->create();

        return Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
        ]);
    }

    public function test_compute_ca_total_sums_normalized_scores_in_ca_groups(): void
    {
        $offering = $this->createOfferingWithGroups();
        $enrollment = $this->createEnrollment($offering);

        $caGroup = AssessmentGroup::factory()->create([
            'course_offering_id' => $offering->id,
            'type' => 'ca',
            'weight_percentage' => 100,
        ]);

        $a1 = Assessment::factory()->create([
            'assessment_group_id' => $caGroup->id,
            'course_id' => $offering->course_id,
            'max_raw_score' => 50,
            'normalized_to' => 25,
        ]);
        $a2 = Assessment::factory()->create([
            'assessment_group_id' => $caGroup->id,
            'course_id' => $offering->course_id,
            'max_raw_score' => 100,
            'normalized_to' => 25,
        ]);

        GradeResult::factory()->create([
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $a1->id,
            'raw_score' => 40,
            'normalized_score' => 20.00, // 40/50 * 25 = 20
        ]);
        GradeResult::factory()->create([
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $a2->id,
            'raw_score' => 80,
            'normalized_score' => 20.00, // 80/100 * 25 = 20
        ]);

        $total = $this->gradingService->computeCaTotal($enrollment);

        $this->assertEquals(40.00, $total);
    }

    public function test_compute_ca_total_respects_ca_override(): void
    {
        $offering = $this->createOfferingWithGroups();
        $enrollment = $this->createEnrollment($offering);
        $enrollment->update(['ca_override' => 42.50]);

        $caGroup = AssessmentGroup::factory()->create([
            'course_offering_id' => $offering->id,
            'type' => 'ca',
        ]);
        Assessment::factory()->create([
            'assessment_group_id' => $caGroup->id,
            'course_id' => $offering->course_id,
        ]);

        $total = $this->gradingService->computeCaTotal($enrollment);

        $this->assertEquals(42.50, $total);
    }

    public function test_compute_ca_total_returns_null_when_no_ca_groups(): void
    {
        $offering = $this->createOfferingWithGroups();
        $enrollment = $this->createEnrollment($offering);

        $total = $this->gradingService->computeCaTotal($enrollment);

        $this->assertNull($total);
    }

    public function test_compute_exam_total_sums_exam_group_scores(): void
    {
        $offering = $this->createOfferingWithGroups();
        $enrollment = $this->createEnrollment($offering);

        $examGroup = AssessmentGroup::factory()->create([
            'course_offering_id' => $offering->id,
            'type' => 'exam',
        ]);

        $exam = Assessment::factory()->create([
            'assessment_group_id' => $examGroup->id,
            'course_id' => $offering->course_id,
            'max_raw_score' => 100,
            'normalized_to' => 100,
        ]);

        GradeResult::factory()->create([
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $exam->id,
            'raw_score' => 75,
            'normalized_score' => 75.00,
        ]);

        $total = $this->gradingService->computeExamTotal($enrollment);

        $this->assertEquals(75.00, $total);
    }

    public function test_compute_exam_total_falls_back_to_exam_score_field(): void
    {
        $offering = $this->createOfferingWithGroups();
        $enrollment = $this->createEnrollment($offering);
        $enrollment->update(['exam_score' => 68.50]);

        $total = $this->gradingService->computeExamTotal($enrollment);

        $this->assertEquals(68.50, $total);
    }

    public function test_compute_final_mark_weighted_sum(): void
    {
        $offering = $this->createOfferingWithGroups(caWeight: 40, examWeight: 60);
        $enrollment = $this->createEnrollment($offering);

        $caGroup = AssessmentGroup::factory()->create([
            'course_offering_id' => $offering->id,
            'type' => 'ca',
        ]);
        $caAssessment = Assessment::factory()->create([
            'assessment_group_id' => $caGroup->id,
            'course_id' => $offering->course_id,
            'max_raw_score' => 100,
            'normalized_to' => null,
        ]);
        GradeResult::factory()->create([
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $caAssessment->id,
            'raw_score' => 80,
            'normalized_score' => 80.00,
        ]);

        $enrollment->update(['exam_score' => 70]);

        // final = (80 * 40 + 70 * 60) / 100 = (3200 + 4200) / 100 = 74
        $final = $this->gradingService->computeFinalMark($enrollment);

        $this->assertEquals(74.00, $final);
    }

    public function test_compute_final_mark_respects_final_override(): void
    {
        $offering = $this->createOfferingWithGroups();
        $enrollment = $this->createEnrollment($offering);
        $enrollment->update(['final_override' => 85.00]);

        $final = $this->gradingService->computeFinalMark($enrollment);

        $this->assertEquals(85.00, $final);
    }

    public function test_resolve_grade_writes_back_all_fields(): void
    {
        $offering = $this->createOfferingWithGroups(caWeight: 50, examWeight: 50);
        $enrollment = $this->createEnrollment($offering);

        $caGroup = AssessmentGroup::factory()->create([
            'course_offering_id' => $offering->id,
            'type' => 'ca',
        ]);
        $caAssessment = Assessment::factory()->create([
            'assessment_group_id' => $caGroup->id,
            'course_id' => $offering->course_id,
            'max_raw_score' => 100,
            'normalized_to' => null,
        ]);
        GradeResult::factory()->create([
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $caAssessment->id,
            'raw_score' => 80,
            'normalized_score' => 80.00,
        ]);
        $enrollment->update(['exam_score' => 70]);

        $resolved = $this->gradingService->resolveGrade($enrollment);

        // final = (80*50 + 70*50)/100 = 75
        $this->assertEquals(80.00, (float) $resolved->ca_total);
        $this->assertEquals(75.00, (float) $resolved->final_total);
        $this->assertEquals('B+', $resolved->final_grade);
        $this->assertEquals(3.5, (float) $resolved->grade_points);
    }

    public function test_resolve_grade_uses_custom_grading_scheme(): void
    {
        $scheme = GradingScheme::factory()->create(['is_default' => false]);
        GradingSchemeLevel::factory()->create([
            'grading_scheme_id' => $scheme->id,
            'letter' => 'A',
            'min_mark' => 70,
            'max_mark' => 100,
            'grade_points' => 5.0,
        ]);
        GradingSchemeLevel::factory()->create([
            'grading_scheme_id' => $scheme->id,
            'letter' => 'B',
            'min_mark' => 50,
            'max_mark' => 69,
            'grade_points' => 3.0,
        ]);
        GradingSchemeLevel::factory()->create([
            'grading_scheme_id' => $scheme->id,
            'letter' => 'F',
            'min_mark' => 0,
            'max_mark' => 49,
            'grade_points' => 0.0,
        ]);

        $offering = $this->createOfferingWithGroups(scheme: $scheme);
        $enrollment = $this->createEnrollment($offering);
        $enrollment->update(['exam_score' => 75, 'final_override' => 75]);

        $resolved = $this->gradingService->resolveGrade($enrollment);

        $this->assertEquals('A', $resolved->final_grade);
        $this->assertEquals(5.0, (float) $resolved->grade_points);
    }

    public function test_resolve_grade_handles_no_grades(): void
    {
        $offering = $this->createOfferingWithGroups();
        $enrollment = $this->createEnrollment($offering);

        $resolved = $this->gradingService->resolveGrade($enrollment);

        $this->assertNull($resolved->final_total);
        $this->assertNull($resolved->final_grade);
        $this->assertNull($resolved->grade_points);
    }

    public function test_resolve_all_grades_processes_all_enrollments(): void
    {
        $offering = $this->createOfferingWithGroups(caWeight: 50, examWeight: 50);

        $e1 = $this->createEnrollment($offering);
        $e1->update(['exam_score' => 80, 'final_override' => 80]);

        $e2 = $this->createEnrollment($offering);
        $e2->update(['exam_score' => 60, 'final_override' => 60]);

        $count = $this->gradingService->resolveAllGrades($offering);

        $this->assertEquals(2, $count);

        $e1->refresh();
        $e2->refresh();

        $this->assertEquals('A', $e1->final_grade);
        $this->assertEquals('B', $e2->final_grade);
    }

    public function test_compute_ca_total_skips_excused_results(): void
    {
        $offering = $this->createOfferingWithGroups();
        $enrollment = $this->createEnrollment($offering);

        $caGroup = AssessmentGroup::factory()->create([
            'course_offering_id' => $offering->id,
            'type' => 'ca',
        ]);

        $a1 = Assessment::factory()->create([
            'assessment_group_id' => $caGroup->id,
            'course_id' => $offering->course_id,
            'max_raw_score' => 100,
            'normalized_to' => null,
        ]);
        $a2 = Assessment::factory()->create([
            'assessment_group_id' => $caGroup->id,
            'course_id' => $offering->course_id,
            'max_raw_score' => 100,
            'normalized_to' => null,
        ]);

        GradeResult::factory()->create([
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $a1->id,
            'raw_score' => 30,
            'normalized_score' => 30.00,
        ]);
        GradeResult::factory()->create([
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $a2->id,
            'raw_score' => 50,
            'normalized_score' => null,
            'is_excused' => true,
        ]);

        $total = $this->gradingService->computeCaTotal($enrollment);

        $this->assertEquals(30.00, $total);
    }
}
