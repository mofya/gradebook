<?php

namespace Tests\Unit\Models;

use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeResult;
use App\Models\Student;
use App\Services\GradingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentTest extends TestCase
{
    use RefreshDatabase;

    public function test_weighted_average_with_multiple_assessments(): void
    {
        $course = Course::factory()->create();
        $courseOffering = CourseOffering::factory()->create(['course_id' => $course->id]);
        $group = AssessmentGroup::factory()->create([
            'course_offering_id' => $courseOffering->id,
            'type' => 'ca',
        ]);
        $student = Student::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $courseOffering->id,
        ]);

        $assessment1 = Assessment::factory()->create([
            'course_id' => $course->id,
            'assessment_group_id' => $group->id,
            'weight' => 40,
            'max_raw_score' => 100,
        ]);
        $assessment2 = Assessment::factory()->create([
            'course_id' => $course->id,
            'assessment_group_id' => $group->id,
            'weight' => 60,
            'max_raw_score' => 100,
        ]);

        GradeResult::factory()->create([
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $assessment1->id,
            'raw_score' => 80,
        ]);
        GradeResult::factory()->create([
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $assessment2->id,
            'raw_score' => 90,
        ]);

        $service = app(GradingService::class);
        $caTotal = $service->computeCaTotal($enrollment);

        // weighted average: (80*40 + 90*60) / (40+60) = 86
        $this->assertEquals(86, $caTotal);
    }

    public function test_returns_null_when_no_grades(): void
    {
        $course = Course::factory()->create();
        $courseOffering = CourseOffering::factory()->create(['course_id' => $course->id]);
        $student = Student::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $courseOffering->id,
        ]);

        $service = app(GradingService::class);
        $this->assertNull($service->computeCaTotal($enrollment));
    }

    public function test_partial_assessments(): void
    {
        $course = Course::factory()->create();
        $courseOffering = CourseOffering::factory()->create(['course_id' => $course->id]);
        $group = AssessmentGroup::factory()->create([
            'course_offering_id' => $courseOffering->id,
            'type' => 'ca',
        ]);
        $student = Student::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $courseOffering->id,
        ]);

        $assessment1 = Assessment::factory()->create([
            'course_id' => $course->id,
            'assessment_group_id' => $group->id,
            'weight' => 40,
            'max_raw_score' => 100,
        ]);
        Assessment::factory()->create([
            'course_id' => $course->id,
            'assessment_group_id' => $group->id,
            'weight' => 60,
            'max_raw_score' => 100,
        ]);

        GradeResult::factory()->create([
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $assessment1->id,
            'raw_score' => 80,
        ]);

        $service = app(GradingService::class);
        $caTotal = $service->computeCaTotal($enrollment);

        // Only one assessment submitted with weight 40, score 80 → weighted avg = 80
        $this->assertEquals(80, $caTotal);
    }

    public function test_only_includes_grades_for_specified_enrollment(): void
    {
        $course1 = Course::factory()->create();
        $course2 = Course::factory()->create();
        $offering1 = CourseOffering::factory()->create(['course_id' => $course1->id]);
        $offering2 = CourseOffering::factory()->create(['course_id' => $course2->id]);
        $group1 = AssessmentGroup::factory()->create([
            'course_offering_id' => $offering1->id,
            'type' => 'ca',
        ]);
        $group2 = AssessmentGroup::factory()->create([
            'course_offering_id' => $offering2->id,
            'type' => 'ca',
        ]);
        $student = Student::factory()->create();
        $enrollment1 = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $offering1->id,
        ]);
        $enrollment2 = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $offering2->id,
        ]);

        $assessment1 = Assessment::factory()->create([
            'course_id' => $course1->id,
            'assessment_group_id' => $group1->id,
            'weight' => 100,
            'max_raw_score' => 100,
        ]);
        $assessment2 = Assessment::factory()->create([
            'course_id' => $course2->id,
            'assessment_group_id' => $group2->id,
            'weight' => 100,
            'max_raw_score' => 100,
        ]);

        GradeResult::factory()->create([
            'enrollment_id' => $enrollment1->id,
            'assessment_id' => $assessment1->id,
            'raw_score' => 90,
        ]);
        GradeResult::factory()->create([
            'enrollment_id' => $enrollment2->id,
            'assessment_id' => $assessment2->id,
            'raw_score' => 50,
        ]);

        $service = app(GradingService::class);
        $this->assertEquals(90, $service->computeCaTotal($enrollment1));
        $this->assertEquals(50, $service->computeCaTotal($enrollment2));
    }

    public function test_single_assessment_at_full_weight(): void
    {
        $course = Course::factory()->create();
        $courseOffering = CourseOffering::factory()->create(['course_id' => $course->id]);
        $group = AssessmentGroup::factory()->create([
            'course_offering_id' => $courseOffering->id,
            'type' => 'ca',
        ]);
        $student = Student::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $courseOffering->id,
        ]);

        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'assessment_group_id' => $group->id,
            'weight' => 100,
            'max_raw_score' => 100,
        ]);

        GradeResult::factory()->create([
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $assessment->id,
            'raw_score' => 75,
        ]);

        $service = app(GradingService::class);
        $this->assertEquals(75, $service->computeCaTotal($enrollment));
    }
}
