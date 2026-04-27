<?php

namespace Tests\Feature\Api;

use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\AssessmentSubsection;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeAuditLog;
use App\Models\GradeResult;
use App\Models\GradingScheme;
use App\Models\GradingSchemeLevel;
use App\Models\MissedAssessmentAppeal;
use App\Models\MissedAssessmentAppealItem;
use App\Models\Semester;
use App\Models\Student;
use App\Models\SubsectionScore;
use App\Models\UnmatchedLabGrade;
use App\Models\User;
use App\Models\Year;
use App\Services\GradingService;
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

    public function test_public_grade_link_generate_extend_revoke(): void
    {
        // Generate
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/offerings/'.$this->offering->id.'/public-grade-link', [
                'action' => 'generate',
                'expiry_days' => 30,
            ])
            ->assertOk()
            ->assertJsonPath('data.class_grades_url', fn ($url) => is_string($url) && str_contains($url, '/class-grades/'));

        $this->offering->refresh();
        $this->assertNotNull($this->offering->public_grade_token);

        // Get
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/offerings/'.$this->offering->id.'/public-grade-link')
            ->assertOk()
            ->assertJsonPath('data.active', true);

        // Extend
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/offerings/'.$this->offering->id.'/public-grade-link', [
                'action' => 'extend',
                'expiry_days' => 7,
            ])
            ->assertOk();

        // Revoke
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/offerings/'.$this->offering->id.'/public-grade-link', [
                'action' => 'revoke',
            ])
            ->assertOk();

        $this->assertNull($this->offering->fresh()->public_grade_token);
    }

    public function test_appeals_endpoint_returns_appeals_with_items(): void
    {
        $student = Student::factory()->create([
            'student_id_number' => '2023000999',
            'first_name' => 'Test',
            'last_name' => 'Student',
            'email' => 'teststudent@example.com',
        ]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
        ]);
        $group = AssessmentGroup::factory()->create(['course_offering_id' => $this->offering->id]);
        $assessment = Assessment::factory()->create([
            'assessment_group_id' => $group->id,
            'name' => 'Lab Test',
        ]);

        $appeal = MissedAssessmentAppeal::factory()->create([
            'course_offering_id' => $this->offering->id,
            'student_id' => $student->id,
            'narrative' => 'Hospitalised.',
        ]);
        MissedAssessmentAppealItem::factory()->create([
            'missed_assessment_appeal_id' => $appeal->id,
            'assessment_id' => $assessment->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/offerings/'.$this->offering->id.'/appeals');

        $response->assertOk()
            ->assertJsonPath('data.0.student_id_number', '2023000999')
            ->assertJsonPath('data.0.student_email', 'teststudent@example.com')
            ->assertJsonPath('data.0.narrative', 'Hospitalised.')
            ->assertJsonPath('data.0.items.0.assessment_name', 'Lab Test');
    }

    public function test_import_lab_grades_matches_by_student_id_number(): void
    {
        $student = Student::factory()->create([
            'student_id_number' => '2023000645',
            'github_username' => null,
        ]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/offerings/'.$this->offering->id.'/lab-grades', [
                'assessment_name' => 'Lab by Student ID',
                'grades' => [
                    ['student_id' => '2023000645', 'final_score' => 77.5],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.grades_imported', 1)
            ->assertJsonPath('data.skipped', 0);
    }

    public function test_import_lab_grades_prefers_student_id_over_github_username(): void
    {
        $student = Student::factory()->create([
            'student_id_number' => '2023000645',
            'github_username' => 'primary-handle',
        ]);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
        ]);

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/offerings/'.$this->offering->id.'/lab-grades', [
                'assessment_name' => 'Lab SID Priority',
                'grades' => [
                    [
                        'student_id' => '2023000645',
                        'github_username' => 'stale-bad-handle',
                        'final_score' => 88,
                    ],
                ],
            ]);

        $response->assertOk()->assertJsonPath('data.grades_imported', 1);
    }

    public function test_import_lab_grades_validation_requires_student_id_or_github_username(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/offerings/'.$this->offering->id.'/lab-grades', [
                'assessment_name' => 'No Identifier',
                'grades' => [
                    ['final_score' => 90],
                ],
            ]);

        $response->assertUnprocessable();
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
            ->assertJsonStructure(['data' => ['mode', 'stats', 'distribution', 'assessment_stats']]);
    }

    public function test_grade_summary_defaults_to_ca_mode(): void
    {
        $this->seedCaFixture();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/offerings/'.$this->offering->id.'/grade-summary');

        $response->assertOk()
            ->assertJsonPath('data.mode', 'ca')
            // Student 1: raw 80 (out of 100) → CA% = 80 → A
            // Student 2: raw 40 → CA% = 40 → D
            // Student 3: no grade result yet → CA% = 0 → D (but counted as pending)
            ->assertJsonPath('data.stats.highest', 80)
            ->assertJsonPath('data.stats.lowest', 40)
            ->assertJsonPath('data.stats.average', 60)
            ->assertJsonPath('data.stats.graded', 2)
            ->assertJsonPath('data.stats.pending', 1)
            ->assertJsonPath('data.distribution.A', 1)
            ->assertJsonPath('data.distribution.D', 1)
            ->assertJsonPath('data.stats.pass_count', 1)
            ->assertJsonPath('data.stats.fail_count', 1);
    }

    public function test_grade_summary_ca_mode_explicit(): void
    {
        $this->seedCaFixture();

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/offerings/'.$this->offering->id.'/grade-summary?mode=ca');

        $response->assertOk()
            ->assertJsonPath('data.mode', 'ca')
            ->assertJsonPath('data.stats.highest', 80);
    }

    public function test_grade_summary_final_mode_uses_final_total(): void
    {
        $this->seedCaFixture();

        // Seed final_total directly on one enrollment to prove final mode reads it.
        Enrollment::where('course_offering_id', $this->offering->id)
            ->orderBy('id')
            ->limit(1)
            ->update(['final_total' => 42.5, 'final_grade' => 'D']);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/offerings/'.$this->offering->id.'/grade-summary?mode=final');

        $response->assertOk()
            ->assertJsonPath('data.mode', 'final')
            ->assertJsonPath('data.stats.highest', 42.5);
    }

    public function test_grade_summary_invalid_mode_returns_422(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/offerings/'.$this->offering->id.'/grade-summary?mode=bogus')
            ->assertStatus(422);
    }

    /**
     * Seed a simple CA fixture: grading scheme with levels, one CA group (weight 100%),
     * one assessment (max 100), and three enrollments — two with raw scores, one without.
     */
    private function seedCaFixture(): void
    {
        $scheme = GradingScheme::factory()->create();

        foreach ([
            ['letter' => 'A', 'min_mark' => 75, 'max_mark' => 100, 'grade_points' => 4.0],
            ['letter' => 'B', 'min_mark' => 65, 'max_mark' => 74, 'grade_points' => 3.0],
            ['letter' => 'C', 'min_mark' => 55, 'max_mark' => 64, 'grade_points' => 2.0],
            ['letter' => 'D', 'min_mark' => 0, 'max_mark' => 54, 'grade_points' => 0.0],
        ] as $i => $level) {
            GradingSchemeLevel::factory()->create([
                'grading_scheme_id' => $scheme->id,
                'letter' => $level['letter'],
                'min_mark' => $level['min_mark'],
                'max_mark' => $level['max_mark'],
                'grade_points' => $level['grade_points'],
                'sort_order' => $i,
            ]);
        }

        $this->offering->update(['grading_scheme_id' => $scheme->id, 'ca_weight' => 100, 'exam_weight' => 0]);

        $group = AssessmentGroup::factory()->create([
            'course_offering_id' => $this->offering->id,
            'type' => 'ca',
            'weight_percentage' => 100,
        ]);

        $assessment = Assessment::factory()->create([
            'assessment_group_id' => $group->id,
            'course_id' => $this->offering->course_id,
            'max_raw_score' => 100,
            'weight' => 1,
        ]);

        foreach ([80, 40, null] as $rawScore) {
            $student = Student::factory()->create();
            $enrollment = Enrollment::factory()->create([
                'student_id' => $student->id,
                'course_offering_id' => $this->offering->id,
            ]);

            if ($rawScore !== null) {
                GradeResult::factory()->create([
                    'enrollment_id' => $enrollment->id,
                    'assessment_id' => $assessment->id,
                    'raw_score' => $rawScore,
                    'is_excused' => false,
                ]);
            }
        }
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

    // --- Create offering endpoint ---

    public function test_create_offering(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/offerings', [
                'course_id' => $this->offering->course_id,
                'semester_id' => $this->offering->semester_id,
                'ca_weight' => 60,
                'exam_weight' => 40,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.ca_weight', '60.00')
            ->assertJsonPath('data.status', 'draft');
    }

    public function test_create_offering_validates_weight_sum(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/offerings', [
                'course_id' => $this->offering->course_id,
                'semester_id' => $this->offering->semester_id,
                'ca_weight' => 60,
                'exam_weight' => 30,
            ])
            ->assertStatus(422)
            ->assertJsonPath('error', 'CA weight and exam weight must sum to 100.');
    }

    // --- Get verification link endpoint ---

    public function test_get_verification_link_when_active(): void
    {
        $this->offering->generateVerificationToken(3);

        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/offerings/'.$this->offering->id.'/verification-link');

        $response->assertOk()
            ->assertJsonPath('data.active', true)
            ->assertJsonStructure(['data' => ['verify_url', 'grades_url', 'expires_at', 'time_remaining']]);
    }

    public function test_get_verification_link_when_none_exists(): void
    {
        $response = $this->actingAs($this->user, 'sanctum')
            ->getJson('/api/v1/offerings/'.$this->offering->id.'/verification-link');

        $response->assertOk()
            ->assertJsonPath('data.active', false);
    }

    // --- Extend verification link ---

    public function test_extend_verification_link(): void
    {
        $this->offering->generateVerificationToken(3);
        $originalToken = $this->offering->verification_token;

        $response = $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/offerings/'.$this->offering->id.'/verification-link', [
                'action' => 'extend',
                'expiry_days' => 10,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.message', 'Verification link extended.');

        $this->offering->refresh();
        $this->assertEquals($originalToken, $this->offering->verification_token);
        $this->assertTrue($this->offering->verification_expires_at->isAfter(now()->addDays(9)));
    }

    public function test_extend_fails_when_no_token_exists(): void
    {
        $this->actingAs($this->user, 'sanctum')
            ->postJson('/api/v1/offerings/'.$this->offering->id.'/verification-link', [
                'action' => 'extend',
                'expiry_days' => 5,
            ])
            ->assertStatus(422);
    }

    // --- Clear single student grade endpoint ---

    public function test_clear_student_grade_deletes_grade_and_recomputes_final_total(): void
    {
        [$student, $enrollment, $assessment] = $this->seedClearStudentGradeFixture(rawScore: 80);

        $this->assertEqualsWithDelta(80.0, (float) $enrollment->fresh()->final_total, 0.01);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson(
                '/api/v1/offerings/'.$this->offering->id
                .'/lab-grades/'.$assessment->id
                .'/students/'.$student->student_id_number,
                ['reason' => 'Mistakenly recorded — see appeal #42.']
            );

        $response->assertOk()
            ->assertJsonPath('data.action', 'deleted')
            ->assertJsonPath('data.student_id_number', $student->student_id_number)
            ->assertJsonPath('data.assessment_name', $assessment->name)
            ->assertJsonPath('data.previous_score', '80.00');

        $this->assertDatabaseMissing('grade_results', [
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $assessment->id,
        ]);

        $this->assertEqualsWithDelta(0.0, (float) $enrollment->fresh()->final_total, 0.01);
    }

    public function test_clear_student_grade_deletes_subsection_scores(): void
    {
        [$student, $enrollment, $assessment] = $this->seedClearStudentGradeFixture(rawScore: 80);

        $gradeResult = GradeResult::where('enrollment_id', $enrollment->id)
            ->where('assessment_id', $assessment->id)
            ->first();

        $subsection = AssessmentSubsection::factory()->create([
            'assessment_id' => $assessment->id,
            'name' => 'Visible Tests (%)',
            'max_score' => 100,
        ]);

        SubsectionScore::factory()->create([
            'grade_result_id' => $gradeResult->id,
            'assessment_subsection_id' => $subsection->id,
            'score' => 95,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson(
                '/api/v1/offerings/'.$this->offering->id
                .'/lab-grades/'.$assessment->id
                .'/students/'.$student->student_id_number
            )
            ->assertOk();

        $this->assertDatabaseMissing('subsection_scores', ['grade_result_id' => $gradeResult->id]);
    }

    public function test_clear_student_grade_is_idempotent_when_no_grade_exists(): void
    {
        [$student, , $assessment] = $this->seedClearStudentGradeFixture(rawScore: null);

        $response = $this->actingAs($this->user, 'sanctum')
            ->deleteJson(
                '/api/v1/offerings/'.$this->offering->id
                .'/lab-grades/'.$assessment->id
                .'/students/'.$student->student_id_number
            );

        $response->assertOk()
            ->assertJsonPath('data.action', 'no_change')
            ->assertJsonPath('data.student_id_number', $student->student_id_number)
            ->assertJsonPath('data.assessment_name', $assessment->name);
    }

    public function test_clear_student_grade_returns_404_when_student_not_enrolled(): void
    {
        [, , $assessment] = $this->seedClearStudentGradeFixture(rawScore: 80);

        Student::factory()->create(['student_id_number' => 'OUTSIDE001']);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson(
                '/api/v1/offerings/'.$this->offering->id
                .'/lab-grades/'.$assessment->id
                .'/students/OUTSIDE001'
            )
            ->assertNotFound();
    }

    public function test_clear_student_grade_returns_404_when_assessment_belongs_to_other_offering(): void
    {
        [$student] = $this->seedClearStudentGradeFixture(rawScore: 80);

        $otherOffering = CourseOffering::factory()->create(['lecturer_id' => $this->user->id]);
        $otherGroup = AssessmentGroup::factory()->create([
            'course_offering_id' => $otherOffering->id,
            'type' => 'ca',
        ]);
        $otherAssessment = Assessment::factory()->create([
            'assessment_group_id' => $otherGroup->id,
            'course_id' => $otherOffering->course_id,
        ]);

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson(
                '/api/v1/offerings/'.$this->offering->id
                .'/lab-grades/'.$otherAssessment->id
                .'/students/'.$student->student_id_number
            )
            ->assertNotFound();
    }

    public function test_clear_student_grade_forbidden_for_non_lecturer(): void
    {
        [$student, , $assessment] = $this->seedClearStudentGradeFixture(rawScore: 80);

        $studentUser = User::factory()->student()->create();

        $this->actingAs($studentUser, 'sanctum')
            ->deleteJson(
                '/api/v1/offerings/'.$this->offering->id
                .'/lab-grades/'.$assessment->id
                .'/students/'.$student->student_id_number
            )
            ->assertForbidden();
    }

    public function test_clear_student_grade_writes_audit_log(): void
    {
        [$student, $enrollment, $assessment] = $this->seedClearStudentGradeFixture(rawScore: 73.5);

        $gradeResultId = GradeResult::where('enrollment_id', $enrollment->id)
            ->where('assessment_id', $assessment->id)
            ->value('id');

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson(
                '/api/v1/offerings/'.$this->offering->id
                .'/lab-grades/'.$assessment->id
                .'/students/'.$student->student_id_number,
                ['reason' => 'Approved missed-assessment appeal.']
            )
            ->assertOk();

        $log = GradeAuditLog::where('auditable_type', GradeResult::class)
            ->where('auditable_id', $gradeResultId)
            ->where('action', 'grade_cleared')
            ->latest('id')
            ->first();

        $this->assertNotNull($log, 'Audit log entry for grade_cleared was not written.');
        $this->assertSame($this->user->id, $log->user_id);
        $this->assertSame('Approved missed-assessment appeal.', $log->reason);
        $this->assertSame('73.50', (string) ($log->old_values['raw_score'] ?? null));
        $this->assertArrayHasKey('raw_score', $log->new_values);
        $this->assertNull($log->new_values['raw_score']);
    }

    public function test_clear_student_grade_makes_assessment_appeal_eligible(): void
    {
        [$student, $enrollment, $assessment] = $this->seedClearStudentGradeFixture(rawScore: 0);

        // Mirror MissedAssessmentAppealForm.php:244 — its filter for "already-graded" assessments.
        $gradedBefore = GradeResult::where('enrollment_id', $enrollment->id)
            ->whereNotNull('raw_score')
            ->pluck('assessment_id')
            ->all();

        $this->assertContains(
            $assessment->id,
            $gradedBefore,
            'Setup precondition: assessment should be marked as graded before clearing.'
        );

        $this->actingAs($this->user, 'sanctum')
            ->deleteJson(
                '/api/v1/offerings/'.$this->offering->id
                .'/lab-grades/'.$assessment->id
                .'/students/'.$student->student_id_number
            )
            ->assertOk();

        $gradedAfter = GradeResult::where('enrollment_id', $enrollment->id)
            ->whereNotNull('raw_score')
            ->pluck('assessment_id')
            ->all();

        $this->assertNotContains(
            $assessment->id,
            $gradedAfter,
            'After clearing, the assessment should be appeal-eligible (not in the whereNotNull(raw_score) set).'
        );
    }

    /**
     * Seed a minimal fixture for the clearStudentGrade endpoint:
     * one enrolled student, one CA group with weight 100%, one assessment, optional grade result.
     *
     * @return array{0: Student, 1: Enrollment, 2: Assessment}
     */
    private function seedClearStudentGradeFixture(?float $rawScore): array
    {
        $scheme = GradingScheme::factory()->create();

        foreach ([
            ['letter' => 'A', 'min_mark' => 75, 'max_mark' => 100, 'grade_points' => 4.0],
            ['letter' => 'B', 'min_mark' => 65, 'max_mark' => 74, 'grade_points' => 3.0],
            ['letter' => 'D', 'min_mark' => 0, 'max_mark' => 64, 'grade_points' => 0.0],
        ] as $i => $level) {
            GradingSchemeLevel::factory()->create([
                'grading_scheme_id' => $scheme->id,
                'letter' => $level['letter'],
                'min_mark' => $level['min_mark'],
                'max_mark' => $level['max_mark'],
                'grade_points' => $level['grade_points'],
                'sort_order' => $i,
            ]);
        }

        $this->offering->update([
            'grading_scheme_id' => $scheme->id,
            'ca_weight' => 100,
            'exam_weight' => 0,
        ]);

        $group = AssessmentGroup::factory()->create([
            'course_offering_id' => $this->offering->id,
            'type' => 'ca',
            'weight_percentage' => 100,
        ]);

        $assessment = Assessment::factory()->create([
            'assessment_group_id' => $group->id,
            'course_id' => $this->offering->course_id,
            'name' => 'Lab 03',
            'max_raw_score' => 100,
            'weight' => 1,
        ]);

        $student = Student::factory()->create([
            'student_id_number' => 'CLR'.fake()->unique()->numerify('######'),
        ]);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
        ]);

        if ($rawScore !== null) {
            GradeResult::factory()->create([
                'enrollment_id' => $enrollment->id,
                'assessment_id' => $assessment->id,
                'raw_score' => $rawScore,
                'is_excused' => false,
            ]);

            // Pre-compute the enrollment's final_total so we can assert recompute happens.
            app(GradingService::class)->resolveGrade($enrollment);
        }

        return [$student, $enrollment->fresh(), $assessment];
    }
}
