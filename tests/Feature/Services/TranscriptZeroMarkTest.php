<?php

namespace Tests\Feature\Services;

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Year;
use App\Services\TranscriptService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TranscriptZeroMarkTest extends TestCase
{
    use RefreshDatabase;

    public function test_zero_mark_is_not_treated_as_null(): void
    {
        $year = Year::factory()->create();
        $semester = Semester::factory()->create(['year_id' => $year->id]);
        $course = Course::factory()->create(['year_id' => $year->id, 'credits' => 3]);
        $offering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'is_published' => true,
        ]);
        $student = Student::factory()->create();

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'final_total' => 0,
            'final_grade' => 'F',
            'grade_points' => 0.0,
        ]);

        $service = app(TranscriptService::class);
        $data = $service->generateTranscriptData($student);

        $this->assertNotEmpty($data['courses'], 'Zero mark enrollment should appear in transcript');
        $courseResult = $data['courses'][0];
        $this->assertNotNull($courseResult['mark'], 'Zero mark should not be null');
        $this->assertSame(0.0, $courseResult['mark']);
        $this->assertSame('F', $courseResult['letter_grade']);
        $this->assertSame(0.0, $courseResult['grade_points']);
    }

    public function test_null_final_total_excluded_from_transcript(): void
    {
        $year = Year::factory()->create();
        $semester = Semester::factory()->create(['year_id' => $year->id]);
        $course = Course::factory()->create(['year_id' => $year->id, 'credits' => 3]);
        $offering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'is_published' => true,
        ]);
        $student = Student::factory()->create();

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
            'final_total' => null,
            'final_grade' => null,
        ]);

        $service = app(TranscriptService::class);
        $data = $service->generateTranscriptData($student);

        $this->assertEmpty($data['courses'], 'Enrollment with null final_total should be excluded');
    }
}
