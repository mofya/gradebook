<?php

namespace Tests\Feature\Services;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\Grade;
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
        $course = Course::factory()->create(['year_id' => $year->id, 'credits' => 3]);
        $assessment = Assessment::factory()->create(['course_id' => $course->id, 'weight' => 100]);
        $student = Student::factory()->create();
        $student->courses()->attach($course->id);

        Grade::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'assessment_id' => $assessment->id,
            'grade' => 0,
        ]);

        $service = app(TranscriptService::class);
        $data = $service->generateTranscriptData($student);

        $courseResult = $data['courses'][0];
        $this->assertNotNull($courseResult['mark'], 'Zero mark should not be null');
        $this->assertSame(0.0, $courseResult['mark']);
        $this->assertSame('D', $courseResult['letter_grade']);
        $this->assertSame(1.0, $courseResult['grade_points']);
    }

    public function test_null_mark_remains_null(): void
    {
        $year = Year::factory()->create();
        $course = Course::factory()->create(['year_id' => $year->id, 'credits' => 3]);
        $student = Student::factory()->create();
        $student->courses()->attach($course->id);

        // No grades entered — totalGradeForCourse returns null
        $service = app(TranscriptService::class);
        $data = $service->generateTranscriptData($student);

        $courseResult = $data['courses'][0];
        $this->assertNull($courseResult['mark']);
        $this->assertNull($courseResult['letter_grade']);
        $this->assertNull($courseResult['grade_points']);
    }
}
