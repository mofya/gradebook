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
use App\Models\UnmatchedLabGrade;
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

    // --- Assessments endpoint ---

    public function test_list_assessments(): void
    {
        $group = AssessmentGroup::factory()->create([
            'course_offering_id' => $this->offering->id,
            'type' => 'ca',
            'name' => 'Labs',
        ]);

        Assessment::factory()->create([
            'assessment_group_id' => $group->id,
            'course_id' => $this->offering->course_id,
            'name' => 'Lab 01',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/offerings/'.$this->offering->id.'/assessments');

        $response->assertOk()
            ->assertJsonPath('data.0.name', 'Labs')
            ->assertJsonPath('data.0.assessments.0.name', 'Lab 01');
    }

    // --- Unmatched endpoint ---

    public function test_list_unmatched_grades(): void
    {
        $group = AssessmentGroup::factory()->create([
            'course_offering_id' => $this->offering->id,
            'type' => 'ca',
        ]);

        $assessment = Assessment::factory()->create([
            'assessment_group_id' => $group->id,
            'course_id' => $this->offering->course_id,
        ]);

        UnmatchedLabGrade::factory()->create([
            'course_offering_id' => $this->offering->id,
            'assessment_id' => $assessment->id,
            'github_username' => 'unknown-user',
            'status' => 'unmatched',
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/offerings/'.$this->offering->id.'/unmatched');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.github_username', 'unknown-user');
    }

    public function test_unmatched_excludes_matched_items(): void
    {
        $group = AssessmentGroup::factory()->create([
            'course_offering_id' => $this->offering->id,
            'type' => 'ca',
        ]);

        $assessment = Assessment::factory()->create([
            'assessment_group_id' => $group->id,
            'course_id' => $this->offering->course_id,
        ]);

        UnmatchedLabGrade::factory()->create([
            'course_offering_id' => $this->offering->id,
            'assessment_id' => $assessment->id,
            'status' => 'matched',
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/offerings/'.$this->offering->id.'/unmatched')
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    // --- Bulk enroll endpoint ---

    public function test_bulk_enroll_students(): void
    {
        $student1 = Student::factory()->create(['student_id_number' => 'SNBULK001']);
        $student2 = Student::factory()->create(['student_id_number' => 'SNBULK002']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/offerings/'.$this->offering->id.'/enrollments', [
                'student_ids' => ['SNBULK001', 'SNBULK002'],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.enrolled', 2)
            ->assertJsonPath('data.already_enrolled', 0)
            ->assertJsonPath('data.not_found', []);

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $student1->id,
            'course_offering_id' => $this->offering->id,
            'source' => 'api',
        ]);
    }

    public function test_bulk_enroll_handles_duplicates_and_not_found(): void
    {
        $student = Student::factory()->create(['student_id_number' => 'SNBULK003']);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/offerings/'.$this->offering->id.'/enrollments', [
                'student_ids' => ['SNBULK003', 'NONEXISTENT'],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.enrolled', 0)
            ->assertJsonPath('data.already_enrolled', 1)
            ->assertJsonPath('data.not_found', ['NONEXISTENT']);
    }

    public function test_bulk_enroll_validates_payload(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/offerings/'.$this->offering->id.'/enrollments', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['student_ids']);
    }

    // --- Status endpoint ---

    public function test_activate_offering_via_api(): void
    {
        $offering = CourseOffering::factory()->create([
            'course_id' => $this->offering->course_id,
            'semester_id' => $this->offering->semester_id,
            'lecturer_id' => $this->user->id,
            'status' => 'draft',
        ]);

        AssessmentGroup::factory()->create(['course_offering_id' => $offering->id]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/v1/offerings/'.$offering->id.'/status', [
                'action' => 'activate',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'active');
    }

    public function test_invalid_status_transition_returns_422(): void
    {
        // Offering is already active (from setUp), can't activate again
        $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/v1/offerings/'.$this->offering->id.'/status', [
                'action' => 'activate',
            ])
            ->assertStatus(422)
            ->assertJsonStructure(['error']);
    }

    public function test_status_validates_action(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/v1/offerings/'.$this->offering->id.'/status', [
                'action' => 'invalid',
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['action']);
    }

    // --- Verification link endpoint ---

    public function test_generate_verification_link_via_api(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/offerings/'.$this->offering->id.'/verification-link', [
                'action' => 'generate',
                'expiry_days' => 5,
            ]);

        $response->assertOk()
            ->assertJsonStructure(['data' => ['verify_url', 'grades_url', 'expires_at']]);

        $this->offering->refresh();
        $this->assertNotNull($this->offering->verification_token);
    }

    public function test_revoke_verification_link_via_api(): void
    {
        $this->offering->generateVerificationToken(3);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/offerings/'.$this->offering->id.'/verification-link', [
                'action' => 'revoke',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.message', 'Verification link revoked.');

        $this->offering->refresh();
        $this->assertNull($this->offering->verification_token);
    }

    public function test_lecturer_cannot_bulk_enroll_another_lecturers_offering(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $otherOffering = CourseOffering::factory()->create(['lecturer_id' => User::factory()->lecturer()->create()->id]);

        $this->actingAs($lecturer, 'sanctum')
            ->postJson('/api/v1/offerings/'.$otherOffering->id.'/enrollments', [
                'student_ids' => ['SN001'],
            ])
            ->assertForbidden();
    }

    // --- Delete lab grades endpoint ---

    public function test_delete_lab_grades(): void
    {
        $group = AssessmentGroup::factory()->create([
            'course_offering_id' => $this->offering->id,
            'type' => 'ca',
        ]);

        $assessment = Assessment::factory()->create([
            'assessment_group_id' => $group->id,
            'course_id' => $this->offering->course_id,
            'name' => 'Lab to delete',
        ]);

        $student = Student::factory()->create();
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
        ]);

        GradeResult::factory()->create([
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $assessment->id,
            'raw_score' => 80,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson('/api/v1/offerings/'.$this->offering->id.'/lab-grades/'.$assessment->id);

        $response->assertOk()
            ->assertJsonPath('data.deleted', 1);

        $this->assertDatabaseMissing('grade_results', [
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $assessment->id,
        ]);
    }

    public function test_delete_lab_grades_returns_404_for_wrong_offering(): void
    {
        $otherOffering = CourseOffering::factory()->create(['lecturer_id' => $this->user->id]);
        $group = AssessmentGroup::factory()->create(['course_offering_id' => $otherOffering->id]);
        $assessment = Assessment::factory()->create([
            'assessment_group_id' => $group->id,
            'course_id' => $otherOffering->course_id,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson('/api/v1/offerings/'.$this->offering->id.'/lab-grades/'.$assessment->id)
            ->assertNotFound();
    }

    // --- Update enrollment (patch GitHub) endpoint ---

    public function test_update_enrollment_github(): void
    {
        $student = Student::factory()->create(['student_id_number' => 'SNPATCH001']);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/v1/offerings/'.$this->offering->id.'/enrollments/SNPATCH001', [
                'github_username' => 'new-github-user',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.github_username', 'new-github-user');

        $this->assertEquals('new-github-user', $student->fresh()->github_username);
    }

    public function test_update_enrollment_rejects_taken_github(): void
    {
        Student::factory()->create(['github_username' => 'taken-user']);
        $student = Student::factory()->create(['student_id_number' => 'SNPATCH002']);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/v1/offerings/'.$this->offering->id.'/enrollments/SNPATCH002', [
                'github_username' => 'taken-user',
            ])
            ->assertStatus(422);
    }

    public function test_update_enrollment_404_for_unenrolled(): void
    {
        Student::factory()->create(['student_id_number' => 'SNPATCH003']);

        $this->actingAs($this->user, 'sanctum')
            ->patchJson('/api/v1/offerings/'.$this->offering->id.'/enrollments/SNPATCH003', [
                'github_username' => 'some-user',
            ])
            ->assertNotFound();
    }

    // --- Grade summary endpoint ---

    public function test_grade_summary(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/offerings/'.$this->offering->id.'/grade-summary');

        $response->assertOk()
            ->assertJsonStructure(['data' => ['stats', 'distribution', 'assessment_stats']]);
    }

    // --- Student profile endpoint ---

    public function test_student_profile_by_student_id(): void
    {
        $student = Student::factory()->create(['student_id_number' => 'SNPROF001', 'github_username' => 'profuser']);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/offerings/'.$this->offering->id.'/students/SNPROF001');

        $response->assertOk()
            ->assertJsonPath('data.student.student_id_number', 'SNPROF001')
            ->assertJsonPath('data.student.github_username', 'profuser')
            ->assertJsonPath('data.enrollment.status', 'enrolled');
    }

    public function test_student_profile_404_for_unenrolled(): void
    {
        Student::factory()->create(['student_id_number' => 'SNPROF002']);

        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/offerings/'.$this->offering->id.'/students/SNPROF002')
            ->assertNotFound();
    }

    // --- Sync enrollments endpoint ---

    public function test_sync_enrollments(): void
    {
        $existing = Student::factory()->create(['student_id_number' => 'SNSYNC001']);
        Enrollment::factory()->create([
            'student_id' => $existing->id,
            'course_offering_id' => $this->offering->id,
        ]);

        $newStudent = Student::factory()->create(['student_id_number' => 'SNSYNC002']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/offerings/'.$this->offering->id.'/enrollments/sync', [
                'student_ids' => ['SNSYNC002'],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.enrolled', 1)
            ->assertJsonCount(1, 'data.not_in_source');

        $this->assertDatabaseHas('enrollments', [
            'student_id' => $newStudent->id,
            'course_offering_id' => $this->offering->id,
        ]);
    }

    public function test_sync_enrollments_reports_not_found(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/offerings/'.$this->offering->id.'/enrollments/sync', [
                'student_ids' => ['NONEXISTENT'],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.not_found', ['NONEXISTENT']);
    }

    // --- Export endpoint ---

    public function test_export_grades(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/offerings/'.$this->offering->id.'/export');

        $response->assertOk();
        $this->assertStringContainsString('spreadsheet', $response->headers->get('content-type'));
    }

    // --- Changelog endpoint ---

    public function test_changelog(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/offerings/'.$this->offering->id.'/changelog');

        $response->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'total']]);
    }

    // --- Authorization for new endpoints ---

    public function test_lecturer_cannot_delete_grades_for_another_lecturers_offering(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $otherOffering = CourseOffering::factory()->create(['lecturer_id' => User::factory()->lecturer()->create()->id]);
        $group = AssessmentGroup::factory()->create(['course_offering_id' => $otherOffering->id]);
        $assessment = Assessment::factory()->create([
            'assessment_group_id' => $group->id,
            'course_id' => $otherOffering->course_id,
        ]);

        $this->actingAs($lecturer, 'sanctum')
            ->deleteJson('/api/v1/offerings/'.$otherOffering->id.'/lab-grades/'.$assessment->id)
            ->assertForbidden();
    }
}
