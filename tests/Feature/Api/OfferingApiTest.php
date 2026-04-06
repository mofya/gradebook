<?php

namespace Tests\Feature\Api;

use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\AssessmentSubsection;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeResult;
use App\Models\Semester;
use App\Models\Student;
use App\Models\SubsectionScore;
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

    public function test_import_lab_grades(): void
    {
        $student = Student::factory()->create(['github_username' => 'testuser123']);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/offerings/'.$this->offering->id.'/lab-grades', [
                'assessment_name' => 'Lab Test Import',
                'grades' => [
                    [
                        'github_username' => 'testuser123',
                        'final_score' => 85.5,
                        'visible_tests' => 90,
                        'hidden_tests' => 80,
                        'code_quality' => 75,
                        'student_feedback' => 'Good work!',
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('message', 'Lab grades imported successfully.')
            ->assertJsonPath('data.grades_imported', 1);
    }

    public function test_import_lab_grades_requires_admin_or_lecturer(): void
    {
        $studentUser = User::factory()->student()->create();

        $response = $this->actingAs($studentUser, 'sanctum')
            ->postJson('/api/v1/offerings/'.$this->offering->id.'/lab-grades', [
                'assessment_name' => 'Lab 01',
                'grades' => [
                    ['github_username' => 'someone', 'final_score' => 50],
                ],
            ]);

        $response->assertForbidden();
    }

    public function test_import_lab_grades_validates_payload(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/offerings/'.$this->offering->id.'/lab-grades', []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['assessment_name', 'grades']);
    }

    public function test_student_grades_by_student_id(): void
    {
        $student = Student::factory()->create(['github_username' => 'jdoe']);
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
            'raw_score' => 92,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/offerings/'.$this->offering->id.'/students/'.$student->student_id_number.'/grades');

        $response->assertOk()
            ->assertJsonPath('data.student.student_id_number', $student->student_id_number)
            ->assertJsonCount(1, 'data.assessments');
    }

    public function test_student_grades_by_github_username(): void
    {
        $student = Student::factory()->create(['github_username' => 'octocat']);
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
            'raw_score' => 78,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/offerings/'.$this->offering->id.'/students/octocat/grades');

        $response->assertOk()
            ->assertJsonPath('data.student.github_username', 'octocat');
    }

    public function test_student_grades_includes_subsections(): void
    {
        $student = Student::factory()->create(['github_username' => 'subsecuser']);
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
            'has_subsections' => true,
        ]);

        $subsection = AssessmentSubsection::factory()->create([
            'assessment_id' => $assessment->id,
            'name' => 'Visible Tests (%)',
            'max_score' => 100,
        ]);

        $gradeResult = GradeResult::factory()->create([
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $assessment->id,
            'raw_score' => 88,
        ]);

        SubsectionScore::factory()->create([
            'grade_result_id' => $gradeResult->id,
            'assessment_subsection_id' => $subsection->id,
            'score' => 95,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/offerings/'.$this->offering->id.'/students/'.$student->student_id_number.'/grades');

        $response->assertOk()
            ->assertJsonPath('data.assessments.0.subsections.0.name', 'Visible Tests (%)')
            ->assertJsonPath('data.assessments.0.subsections.0.score', '95.00');
    }

    public function test_student_grades_returns_404_for_unknown_student(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/offerings/'.$this->offering->id.'/students/NONEXISTENT/grades');

        $response->assertNotFound();
    }

    public function test_student_user_cannot_access_offerings(): void
    {
        $studentUser = User::factory()->student()->create();

        $this->actingAs($studentUser, 'sanctum')
            ->getJson('/api/v1/offerings')
            ->assertForbidden();
    }

    public function test_lecturer_can_list_own_offerings(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $offering = CourseOffering::factory()->create(['lecturer_id' => $lecturer->id]);

        $response = $this->actingAs($lecturer, 'sanctum')
            ->getJson('/api/v1/offerings');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($offering->id, $ids);
    }

    public function test_lecturer_only_sees_own_offerings_in_index(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $ownOffering = CourseOffering::factory()->create(['lecturer_id' => $lecturer->id]);
        $otherOffering = CourseOffering::factory()->create(['lecturer_id' => User::factory()->lecturer()->create()->id]);

        $response = $this->actingAs($lecturer, 'sanctum')
            ->getJson('/api/v1/offerings');

        $response->assertOk();
        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertContains($ownOffering->id, $ids);
        $this->assertNotContains($otherOffering->id, $ids);
    }

    public function test_lecturer_cannot_view_another_lecturers_offering(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $otherLecturer = User::factory()->lecturer()->create();
        $otherOffering = CourseOffering::factory()->create(['lecturer_id' => $otherLecturer->id]);

        $this->actingAs($lecturer, 'sanctum')
            ->getJson('/api/v1/offerings/'.$otherOffering->id)
            ->assertForbidden();
    }

    public function test_lecturer_can_view_own_offering(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $offering = CourseOffering::factory()->create(['lecturer_id' => $lecturer->id]);

        $this->actingAs($lecturer, 'sanctum')
            ->getJson('/api/v1/offerings/'.$offering->id)
            ->assertOk();
    }

    public function test_lecturer_cannot_import_lab_grades_to_another_lecturers_offering(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $otherLecturer = User::factory()->lecturer()->create();
        $otherOffering = CourseOffering::factory()->create(['lecturer_id' => $otherLecturer->id]);

        $this->actingAs($lecturer, 'sanctum')
            ->postJson('/api/v1/offerings/'.$otherOffering->id.'/lab-grades', [
                'assessment_name' => 'Lab 01',
                'grades' => [['github_username' => 'user', 'final_score' => 50]],
            ])
            ->assertForbidden();
    }

    public function test_admin_can_see_all_offerings(): void
    {
        $admin = User::factory()->admin()->create();
        CourseOffering::factory()->count(3)->create();

        $response = $this->actingAs($admin, 'sanctum')
            ->getJson('/api/v1/offerings');

        $response->assertOk();
        $this->assertGreaterThanOrEqual(3, count($response->json('data')));
    }

    public function test_lecturer_cannot_view_enrollments_of_another_lecturers_offering(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $otherOffering = CourseOffering::factory()->create(['lecturer_id' => User::factory()->lecturer()->create()->id]);

        $this->actingAs($lecturer, 'sanctum')
            ->getJson('/api/v1/offerings/'.$otherOffering->id.'/enrollments')
            ->assertForbidden();
    }
}
