<?php

namespace Tests\Feature\Api;

use App\Models\Assessment;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_student_grades(): void
    {
        $user = User::factory()->lecturer()->create();
        $year = Year::factory()->create();
        $course = Course::factory()->create(['year_id' => $year->id]);
        $assessment = Assessment::factory()->create(['course_id' => $course->id]);
        $student = Student::factory()->create();

        Grade::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'assessment_id' => $assessment->id,
            'grade' => 85,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/grades/'.$student->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_lecturer_can_submit_grade(): void
    {
        $user = User::factory()->lecturer()->create();
        $year = Year::factory()->create();
        $course = Course::factory()->create(['year_id' => $year->id]);
        $assessment = Assessment::factory()->create(['course_id' => $course->id]);
        $student = Student::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/grades/store', [
                'student_id' => $student->id,
                'course_id' => $course->id,
                'assessment_id' => $assessment->id,
                'grade' => 78,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.mark', '78.00')
            ->assertJsonPath('data.grade_letter', 'B+');

        $this->assertDatabaseHas('grades', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'grade' => 78,
            'grade_letter' => 'B+',
            'lecturer_id' => $user->id,
        ]);
    }

    public function test_grade_submission_validates_mark_range(): void
    {
        $user = User::factory()->lecturer()->create();
        $year = Year::factory()->create();
        $course = Course::factory()->create(['year_id' => $year->id]);
        $assessment = Assessment::factory()->create(['course_id' => $course->id]);
        $student = Student::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/grades/store', [
                'student_id' => $student->id,
                'course_id' => $course->id,
                'assessment_id' => $assessment->id,
                'grade' => 105,
            ]);

        $response->assertUnprocessable();
    }

    public function test_grade_submission_requires_all_fields(): void
    {
        $user = User::factory()->lecturer()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/grades/store', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['student_id', 'course_id', 'assessment_id', 'grade']);
    }

    public function test_unauthenticated_user_cannot_submit_grades(): void
    {
        $response = $this->postJson('/api/grades/store', []);

        $response->assertUnauthorized();
    }

    public function test_student_can_view_own_grades(): void
    {
        $student = Student::factory()->create();
        $studentUser = User::factory()->student()->create(['email' => $student->email]);
        $year = Year::factory()->create();
        $course = Course::factory()->create(['year_id' => $year->id]);
        $assessment = Assessment::factory()->create(['course_id' => $course->id]);

        Grade::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'assessment_id' => $assessment->id,
            'grade' => 85,
        ]);

        $response = $this->actingAs($studentUser, 'sanctum')
            ->getJson('/api/grades/'.$student->id);

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_student_cannot_view_other_students_grades(): void
    {
        $student = Student::factory()->create();
        $otherStudent = Student::factory()->create();
        $studentUser = User::factory()->student()->create(['email' => $otherStudent->email]);

        $response = $this->actingAs($studentUser, 'sanctum')
            ->getJson('/api/grades/'.$student->id);

        $response->assertForbidden();
    }

    public function test_student_cannot_submit_grades(): void
    {
        $studentUser = User::factory()->student()->create();
        $year = Year::factory()->create();
        $course = Course::factory()->create(['year_id' => $year->id]);
        $assessment = Assessment::factory()->create(['course_id' => $course->id]);
        $student = Student::factory()->create();

        $response = $this->actingAs($studentUser, 'sanctum')
            ->postJson('/api/grades/store', [
                'student_id' => $student->id,
                'course_id' => $course->id,
                'assessment_id' => $assessment->id,
                'grade' => 78,
            ]);

        $response->assertForbidden();
    }

    public function test_admin_can_view_any_student_grades(): void
    {
        $user = User::factory()->admin()->create();
        $student = Student::factory()->create();
        $year = Year::factory()->create();
        $course = Course::factory()->create(['year_id' => $year->id]);
        $assessment = Assessment::factory()->create(['course_id' => $course->id]);

        Grade::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'assessment_id' => $assessment->id,
            'grade' => 70,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/grades/'.$student->id);

        $response->assertOk();
    }
}
