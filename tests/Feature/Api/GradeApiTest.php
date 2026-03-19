<?php

namespace Tests\Feature\Api;

use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeResult;
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
        $courseOffering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'lecturer_id' => $user->id,
        ]);
        $group = AssessmentGroup::factory()->create([
            'course_offering_id' => $courseOffering->id,
            'type' => 'ca',
        ]);
        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'assessment_group_id' => $group->id,
        ]);
        $student = Student::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $courseOffering->id,
        ]);

        GradeResult::factory()->create([
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $assessment->id,
            'raw_score' => 85,
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
        $courseOffering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'lecturer_id' => $user->id,
        ]);
        $group = AssessmentGroup::factory()->create([
            'course_offering_id' => $courseOffering->id,
            'type' => 'ca',
        ]);
        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'assessment_group_id' => $group->id,
            'weight' => 100,
            'max_raw_score' => 100,
        ]);
        $student = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $courseOffering->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/grades/store', [
                'student_id' => $student->id,
                'assessment_id' => $assessment->id,
                'grade' => 78,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.raw_score', '78.00');

        $this->assertDatabaseHas('grade_results', [
            'assessment_id' => $assessment->id,
            'raw_score' => 78,
            'graded_by' => $user->id,
        ]);
    }

    public function test_lecturer_cannot_submit_grade_for_unassigned_course(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $otherLecturer = User::factory()->lecturer()->create();
        $year = Year::factory()->create();
        $course = Course::factory()->create(['year_id' => $year->id]);
        $courseOffering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'lecturer_id' => $otherLecturer->id,
        ]);
        $group = AssessmentGroup::factory()->create([
            'course_offering_id' => $courseOffering->id,
            'type' => 'ca',
        ]);
        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'assessment_group_id' => $group->id,
        ]);
        $student = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $courseOffering->id,
        ]);

        $response = $this->actingAs($lecturer, 'sanctum')
            ->postJson('/api/grades/store', [
                'student_id' => $student->id,
                'assessment_id' => $assessment->id,
                'grade' => 78,
            ]);

        $response->assertForbidden();
    }

    public function test_grade_submission_validates_mark_range(): void
    {
        $user = User::factory()->lecturer()->create();
        $year = Year::factory()->create();
        $course = Course::factory()->create(['year_id' => $year->id]);
        $courseOffering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'lecturer_id' => $user->id,
        ]);
        $group = AssessmentGroup::factory()->create([
            'course_offering_id' => $courseOffering->id,
            'type' => 'ca',
        ]);
        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'assessment_group_id' => $group->id,
        ]);
        $student = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $courseOffering->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->postJson('/api/grades/store', [
                'student_id' => $student->id,
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
            ->assertJsonValidationErrors(['student_id', 'assessment_id', 'grade']);
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
        $courseOffering = CourseOffering::factory()->create(['course_id' => $course->id]);
        $group = AssessmentGroup::factory()->create([
            'course_offering_id' => $courseOffering->id,
            'type' => 'ca',
        ]);
        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'assessment_group_id' => $group->id,
        ]);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $courseOffering->id,
        ]);

        GradeResult::factory()->create([
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $assessment->id,
            'raw_score' => 85,
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
        $courseOffering = CourseOffering::factory()->create(['course_id' => $course->id]);
        $group = AssessmentGroup::factory()->create([
            'course_offering_id' => $courseOffering->id,
            'type' => 'ca',
        ]);
        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'assessment_group_id' => $group->id,
        ]);
        $student = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $courseOffering->id,
        ]);

        $response = $this->actingAs($studentUser, 'sanctum')
            ->postJson('/api/grades/store', [
                'student_id' => $student->id,
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
        $courseOffering = CourseOffering::factory()->create(['course_id' => $course->id]);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $courseOffering->id,
        ]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/grades/'.$student->id);

        $response->assertOk();
    }

    public function test_admin_can_submit_grade_for_any_course(): void
    {
        $admin = User::factory()->admin()->create();
        $otherLecturer = User::factory()->lecturer()->create();
        $year = Year::factory()->create();
        $course = Course::factory()->create(['year_id' => $year->id]);
        $courseOffering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'lecturer_id' => $otherLecturer->id,
        ]);
        $group = AssessmentGroup::factory()->create([
            'course_offering_id' => $courseOffering->id,
            'type' => 'ca',
        ]);
        $assessment = Assessment::factory()->create([
            'course_id' => $course->id,
            'assessment_group_id' => $group->id,
            'weight' => 100,
            'max_raw_score' => 100,
        ]);
        $student = Student::factory()->create();
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $courseOffering->id,
        ]);

        $response = $this->actingAs($admin, 'sanctum')
            ->postJson('/api/grades/store', [
                'student_id' => $student->id,
                'assessment_id' => $assessment->id,
                'grade' => 90,
            ]);

        $response->assertCreated();
    }
}
