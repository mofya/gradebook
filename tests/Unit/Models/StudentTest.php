<?php

namespace Tests\Unit\Models;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Student;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentTest extends TestCase
{
    use RefreshDatabase;

    public function test_weighted_average_with_multiple_assessments(): void
    {
        $course = Course::factory()->create();
        $student = Student::factory()->create();

        $assessment1 = Assessment::factory()->create(['course_id' => $course->id, 'weight' => 40]);
        $assessment2 = Assessment::factory()->create(['course_id' => $course->id, 'weight' => 60]);

        Grade::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'assessment_id' => $assessment1->id,
            'grade' => 80,
        ]);

        Grade::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'assessment_id' => $assessment2->id,
            'grade' => 90,
        ]);

        // (80 * 40/100 + 90 * 60/100) / ((40 + 60) / 100) = (32 + 54) / 1 = 86
        $this->assertEquals(86, $student->totalGradeForCourse($course->id));
    }

    public function test_returns_null_when_no_grades(): void
    {
        $course = Course::factory()->create();
        $student = Student::factory()->create();

        $this->assertNull($student->totalGradeForCourse($course->id));
    }

    public function test_partial_assessments(): void
    {
        $course = Course::factory()->create();
        $student = Student::factory()->create();

        $assessment1 = Assessment::factory()->create(['course_id' => $course->id, 'weight' => 40]);
        Assessment::factory()->create(['course_id' => $course->id, 'weight' => 60]);

        Grade::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'assessment_id' => $assessment1->id,
            'grade' => 80,
        ]);

        // (80 * 40/100) / (40/100) = 32 / 0.4 = 80
        $this->assertEquals(80, $student->totalGradeForCourse($course->id));
    }

    public function test_only_includes_grades_for_specified_course(): void
    {
        $course1 = Course::factory()->create();
        $course2 = Course::factory()->create();
        $student = Student::factory()->create();

        $assessment1 = Assessment::factory()->create(['course_id' => $course1->id, 'weight' => 100]);
        $assessment2 = Assessment::factory()->create(['course_id' => $course2->id, 'weight' => 100]);

        Grade::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course1->id,
            'assessment_id' => $assessment1->id,
            'grade' => 90,
        ]);

        Grade::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course2->id,
            'assessment_id' => $assessment2->id,
            'grade' => 50,
        ]);

        $this->assertEquals(90, $student->totalGradeForCourse($course1->id));
        $this->assertEquals(50, $student->totalGradeForCourse($course2->id));
    }

    public function test_single_assessment_at_full_weight(): void
    {
        $course = Course::factory()->create();
        $student = Student::factory()->create();

        $assessment = Assessment::factory()->create(['course_id' => $course->id, 'weight' => 100]);

        Grade::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'assessment_id' => $assessment->id,
            'grade' => 75,
        ]);

        // (75 * 100/100) / (100/100) = 75
        $this->assertEquals(75, $student->totalGradeForCourse($course->id));
    }
}
