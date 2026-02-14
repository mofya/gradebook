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

class TranscriptApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_transcript_data(): void
    {
        $user = User::factory()->admin()->create();
        $year = Year::factory()->create();
        $course = Course::factory()->create(['year_id' => $year->id, 'credits' => 3]);
        $assessment = Assessment::factory()->create(['course_id' => $course->id, 'weight' => 100]);
        $student = Student::factory()->create();
        $student->courses()->attach($course->id);

        Grade::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'assessment_id' => $assessment->id,
            'grade' => 75,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/transcripts/'.$student->id);

        $response->assertOk()
            ->assertJsonStructure([
                'student' => ['id', 'name', 'email'],
                'courses',
                'cumulative_gpa',
            ]);
    }

    public function test_unauthenticated_user_cannot_access_transcripts(): void
    {
        $student = Student::factory()->create();

        $response = $this->getJson('/api/transcripts/'.$student->id);

        $response->assertUnauthorized();
    }

    public function test_student_can_view_own_transcript(): void
    {
        $student = Student::factory()->create();
        $studentUser = User::factory()->student()->create(['email' => $student->email]);

        $response = $this->actingAs($studentUser, 'sanctum')
            ->getJson('/api/transcripts/'.$student->id);

        $response->assertOk()
            ->assertJsonStructure(['student', 'courses', 'cumulative_gpa']);
    }

    public function test_student_cannot_view_other_students_transcript(): void
    {
        $student = Student::factory()->create();
        $otherStudent = Student::factory()->create();
        $studentUser = User::factory()->student()->create(['email' => $otherStudent->email]);

        $response = $this->actingAs($studentUser, 'sanctum')
            ->getJson('/api/transcripts/'.$student->id);

        $response->assertForbidden();
    }

    public function test_lecturer_can_view_any_transcript(): void
    {
        $user = User::factory()->lecturer()->create();
        $student = Student::factory()->create();

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/transcripts/'.$student->id);

        $response->assertOk();
    }

    public function test_student_cannot_download_other_students_transcript(): void
    {
        $student = Student::factory()->create();
        $otherStudent = Student::factory()->create();
        $studentUser = User::factory()->student()->create(['email' => $otherStudent->email]);

        $response = $this->actingAs($studentUser, 'sanctum')
            ->getJson('/api/transcripts/'.$student->id.'/download');

        $response->assertForbidden();
    }
}
