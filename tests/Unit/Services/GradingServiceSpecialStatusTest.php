<?php

namespace Tests\Unit\Services;

use App\Enums\ExamStatus;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Year;
use App\Services\GradingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradingServiceSpecialStatusTest extends TestCase
{
    use RefreshDatabase;

    private GradingService $gradingService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gradingService = new GradingService;
    }

    private function createEnrollmentWithStatus(?ExamStatus $status = null): Enrollment
    {
        $year = Year::factory()->create();
        $semester = Semester::factory()->create(['year_id' => $year->id]);
        $course = Course::factory()->create(['year_id' => $year->id, 'credits' => 3]);
        $offering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'ca_weight' => 50,
            'exam_weight' => 50,
        ]);
        $student = Student::factory()->create();

        return Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'exam_status' => $status?->value,
            'exam_score' => 70,
            'final_override' => 75,
        ]);
    }

    public function test_normal_entry_computes_grade_normally(): void
    {
        $enrollment = $this->createEnrollmentWithStatus(ExamStatus::NotEntered);

        $resolved = $this->gradingService->resolveGrade($enrollment);

        $this->assertEquals(75.00, (float) $resolved->final_total);
        $this->assertEquals('B+', $resolved->final_grade);
        $this->assertNull($resolved->remarks);
    }

    public function test_supplementary_caps_final_mark_at_50(): void
    {
        $enrollment = $this->createEnrollmentWithStatus(ExamStatus::Supplementary);

        $resolved = $this->gradingService->resolveGrade($enrollment);

        $this->assertEquals(50.00, (float) $resolved->final_total);
        $this->assertEquals('C+', $resolved->final_grade);
        $this->assertEquals('(SP)', $resolved->remarks);
    }

    public function test_supplementary_does_not_cap_if_below_50(): void
    {
        $enrollment = $this->createEnrollmentWithStatus(ExamStatus::Supplementary);
        $enrollment->update(['final_override' => 40]);

        $resolved = $this->gradingService->resolveGrade($enrollment);

        $this->assertEquals(40.00, (float) $resolved->final_total);
        $this->assertEquals('C', $resolved->final_grade);
        $this->assertNull($resolved->remarks);
    }

    public function test_deferred_skips_computation(): void
    {
        $enrollment = $this->createEnrollmentWithStatus(ExamStatus::Deferred);

        $resolved = $this->gradingService->resolveGrade($enrollment);

        $this->assertNull($resolved->final_total);
        $this->assertNull($resolved->final_grade);
        $this->assertNull($resolved->grade_points);
        $this->assertEquals('Deferred', $resolved->remarks);
    }

    public function test_exempt_skips_computation(): void
    {
        $enrollment = $this->createEnrollmentWithStatus(ExamStatus::Exempt);

        $resolved = $this->gradingService->resolveGrade($enrollment);

        $this->assertNull($resolved->final_total);
        $this->assertNull($resolved->final_grade);
        $this->assertNull($resolved->grade_points);
        $this->assertEquals('Exempt', $resolved->remarks);
    }

    public function test_absent_sets_final_mark_to_zero(): void
    {
        $enrollment = $this->createEnrollmentWithStatus(ExamStatus::Absent);

        $resolved = $this->gradingService->resolveGrade($enrollment);

        $this->assertEquals(0.00, (float) $resolved->final_total);
        $this->assertEquals('NE', $resolved->final_grade);
        $this->assertEquals(0.0, (float) $resolved->grade_points);
        $this->assertEquals('Absent', $resolved->remarks);
    }

    public function test_null_exam_status_computes_normally(): void
    {
        $enrollment = $this->createEnrollmentWithStatus(null);

        $resolved = $this->gradingService->resolveGrade($enrollment);

        $this->assertEquals(75.00, (float) $resolved->final_total);
        $this->assertEquals('B+', $resolved->final_grade);
        $this->assertNull($resolved->remarks);
    }

    public function test_supplementary_grade_points_reflect_capped_mark(): void
    {
        $enrollment = $this->createEnrollmentWithStatus(ExamStatus::Supplementary);

        $resolved = $this->gradingService->resolveGrade($enrollment);

        // Capped at 50 → C+ → 2.5 grade points
        $this->assertEquals(2.5, (float) $resolved->grade_points);
    }

    public function test_withheld_sets_grade_to_wh(): void
    {
        $enrollment = $this->createEnrollmentWithStatus(ExamStatus::Withheld);

        $resolved = $this->gradingService->resolveGrade($enrollment);

        $this->assertNull($resolved->final_total);
        $this->assertEquals('WH', $resolved->final_grade);
        $this->assertNull($resolved->grade_points);
        $this->assertEquals('Withheld', $resolved->remarks);
    }
}
