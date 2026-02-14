<?php

namespace Tests\Feature\Api;

use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeResult;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfferingApiTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private CourseOffering $offering;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->admin()->create();

        $year = Year::factory()->create();
        $semester = Semester::factory()->create(['year_id' => $year->id]);
        $course = Course::factory()->create(['year_id' => $year->id]);

        $this->offering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'lecturer_id' => $this->user->id,
        ]);
    }

    public function test_list_offerings(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/offerings');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta']);
    }

    public function test_show_offering(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/offerings/'.$this->offering->id);

        $response->assertOk()
            ->assertJsonPath('data.id', $this->offering->id);
    }

    public function test_get_offering_enrollments(): void
    {
        $student = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/offerings/'.$this->offering->id.'/enrollments');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_get_offering_grades(): void
    {
        $student = Student::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
        ]);

        $group = AssessmentGroup::factory()->create([
            'course_offering_id' => $this->offering->id,
            'type' => 'ca',
        ]);

        $assessment = Assessment::factory()->create([
            'assessment_group_id' => $group->id,
            'course_id' => $this->offering->course_id,
        ]);

        GradeResult::factory()->create([
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $assessment->id,
            'raw_score' => 85,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/offerings/'.$this->offering->id.'/grades');

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }
}
