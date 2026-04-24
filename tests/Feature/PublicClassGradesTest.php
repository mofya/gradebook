<?php

namespace Tests\Feature;

use App\Livewire\PublicClassGrades;
use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeResult;
use App\Models\GradingScheme;
use App\Models\GradingSchemeLevel;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PublicClassGradesTest extends TestCase
{
    use RefreshDatabase;

    private CourseOffering $offering;

    protected function setUp(): void
    {
        parent::setUp();

        $year = Year::factory()->create();
        $semester = Semester::factory()->create(['year_id' => $year->id]);
        $course = Course::factory()->create(['year_id' => $year->id]);

        $this->offering = CourseOffering::factory()->withPublicGradeToken()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
        ]);
    }

    public function test_class_grades_page_loads_with_valid_token(): void
    {
        $response = $this->get(route('class.grades', ['token' => $this->offering->public_grade_token]));

        $response->assertOk();
        $response->assertSee($this->offering->course->code);
        $response->assertSee('Class Grades');
    }

    public function test_expired_token_shows_expired_message(): void
    {
        $offering = CourseOffering::factory()->withPublicGradeToken(-1)->create();

        Livewire::test(PublicClassGrades::class, ['token' => $offering->public_grade_token])
            ->assertSet('step', 'expired')
            ->assertSee('Link Expired');
    }

    public function test_invalid_token_shows_expired(): void
    {
        Livewire::test(PublicClassGrades::class, ['token' => 'nonexistent-token-xyz'])
            ->assertSet('step', 'expired');
    }

    public function test_displays_enrolled_students_with_grades(): void
    {
        $student = Student::factory()->create([
            'student_id_number' => 'CG001',
            'github_username' => 'student-one',
        ]);

        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
        ]);

        $group = AssessmentGroup::factory()->create([
            'course_offering_id' => $this->offering->id,
            'type' => 'ca',
            'name' => 'Labs',
        ]);

        $assessment = Assessment::factory()->create([
            'assessment_group_id' => $group->id,
            'course_id' => $this->offering->course_id,
            'name' => 'Lab 1',
            'max_raw_score' => 50,
        ]);

        GradeResult::factory()->create([
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $assessment->id,
            'raw_score' => 42,
        ]);

        Livewire::test(PublicClassGrades::class, ['token' => $this->offering->public_grade_token])
            ->assertSet('step', 'loaded')
            ->assertSee('CG001')
            ->assertSee('student-one')
            ->assertSee('Lab 1')
            ->assertSee('42.0');
    }

    public function test_exam_assessments_are_excluded(): void
    {
        $student = Student::factory()->create(['student_id_number' => 'CG002']);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
        ]);

        $examGroup = AssessmentGroup::factory()->create([
            'course_offering_id' => $this->offering->id,
            'type' => 'exam',
            'name' => 'Final Exam',
        ]);

        Assessment::factory()->create([
            'assessment_group_id' => $examGroup->id,
            'course_id' => $this->offering->course_id,
            'name' => 'Exam Paper',
        ]);

        Livewire::test(PublicClassGrades::class, ['token' => $this->offering->public_grade_token])
            ->assertSet('step', 'loaded')
            ->assertDontSee('Final Exam')
            ->assertDontSee('Exam Paper');
    }

    public function test_sorting_by_student_id(): void
    {
        $studentA = Student::factory()->create(['student_id_number' => 'AAA111']);
        $studentB = Student::factory()->create(['student_id_number' => 'ZZZ999']);

        Enrollment::factory()->create([
            'student_id' => $studentA->id,
            'course_offering_id' => $this->offering->id,
        ]);

        Enrollment::factory()->create([
            'student_id' => $studentB->id,
            'course_offering_id' => $this->offering->id,
        ]);

        $component = Livewire::test(PublicClassGrades::class, ['token' => $this->offering->public_grade_token])
            ->assertSet('step', 'loaded')
            ->assertSet('sortColumn', 'student_id_number')
            ->assertSet('sortDirection', 'asc');

        $students = $component->get('students');
        $this->assertEquals('AAA111', $students[0]['student_id_number']);
        $this->assertEquals('ZZZ999', $students[1]['student_id_number']);

        $component->call('sort', 'student_id_number');
        $students = $component->get('students');
        $this->assertEquals('ZZZ999', $students[0]['student_id_number']);
        $this->assertEquals('AAA111', $students[1]['student_id_number']);
    }

    public function test_sorting_by_github_username(): void
    {
        $studentA = Student::factory()->create([
            'student_id_number' => 'CG003',
            'github_username' => 'alice',
        ]);
        $studentB = Student::factory()->create([
            'student_id_number' => 'CG004',
            'github_username' => 'zara',
        ]);

        Enrollment::factory()->create([
            'student_id' => $studentA->id,
            'course_offering_id' => $this->offering->id,
        ]);

        Enrollment::factory()->create([
            'student_id' => $studentB->id,
            'course_offering_id' => $this->offering->id,
        ]);

        $component = Livewire::test(PublicClassGrades::class, ['token' => $this->offering->public_grade_token])
            ->call('sort', 'github_username');

        $students = $component->get('students');
        $this->assertEquals('alice', $students[0]['github_username']);
        $this->assertEquals('zara', $students[1]['github_username']);
    }

    public function test_excused_grades_show_correctly(): void
    {
        $student = Student::factory()->create(['student_id_number' => 'CG005']);

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
            'name' => 'Quiz 1',
        ]);

        GradeResult::factory()->create([
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $assessment->id,
            'is_excused' => true,
            'raw_score' => null,
        ]);

        Livewire::test(PublicClassGrades::class, ['token' => $this->offering->public_grade_token])
            ->assertSee('EX');
    }

    public function test_model_generate_public_grade_token(): void
    {
        $offering = CourseOffering::factory()->create();

        $this->assertNull($offering->public_grade_token);

        $offering->generatePublicGradeToken(14);

        $this->assertNotNull($offering->fresh()->public_grade_token);
        $this->assertTrue($offering->fresh()->hasValidPublicGradeToken());
        $this->assertEquals(64, strlen($offering->fresh()->public_grade_token));
    }

    public function test_model_revoke_public_grade_token(): void
    {
        $offering = CourseOffering::factory()->withPublicGradeToken()->create();

        $this->assertTrue($offering->hasValidPublicGradeToken());

        $offering->revokePublicGradeToken();

        $this->assertFalse($offering->fresh()->hasValidPublicGradeToken());
        $this->assertNull($offering->fresh()->public_grade_token);
    }

    public function test_model_extend_public_grade_token(): void
    {
        $offering = CourseOffering::factory()->withPublicGradeToken(1)->create();
        $originalExpiry = $offering->public_grade_token_expires_at;

        $offering->extendPublicGradeToken(30);

        $this->assertTrue($offering->fresh()->public_grade_token_expires_at->isAfter($originalExpiry));
    }

    public function test_model_extend_throws_when_no_token(): void
    {
        $offering = CourseOffering::factory()->create();

        $this->expectException(\LogicException::class);
        $offering->extendPublicGradeToken(7);
    }

    public function test_empty_class_shows_no_students_message(): void
    {
        Livewire::test(PublicClassGrades::class, ['token' => $this->offering->public_grade_token])
            ->assertSet('step', 'loaded')
            ->assertSee('No students enrolled yet');
    }

    public function test_student_names_are_not_exposed(): void
    {
        $student = Student::factory()->create([
            'student_id_number' => 'CG006',
            'first_name' => 'UniqueFirstNameXYZ',
            'last_name' => 'UniqueLastNameXYZ',
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
        ]);

        Livewire::test(PublicClassGrades::class, ['token' => $this->offering->public_grade_token])
            ->assertDontSee('UniqueFirstNameXYZ')
            ->assertDontSee('UniqueLastNameXYZ');
    }

    public function test_gender_column_is_displayed(): void
    {
        $student = Student::factory()->create([
            'student_id_number' => 'CG007',
            'gender' => 'Female',
        ]);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
        ]);

        $component = Livewire::test(PublicClassGrades::class, ['token' => $this->offering->public_grade_token])
            ->assertSee('CG007');

        $students = $component->get('students');
        $this->assertEquals('Female', $students[0]['gender']);
    }

    public function test_search_filters_by_student_id(): void
    {
        $studentA = Student::factory()->create(['student_id_number' => 'SEARCH001']);
        $studentB = Student::factory()->create(['student_id_number' => 'OTHER002']);

        Enrollment::factory()->create([
            'student_id' => $studentA->id,
            'course_offering_id' => $this->offering->id,
        ]);

        Enrollment::factory()->create([
            'student_id' => $studentB->id,
            'course_offering_id' => $this->offering->id,
        ]);

        $component = Livewire::test(PublicClassGrades::class, ['token' => $this->offering->public_grade_token]);

        $this->assertCount(2, $component->get('students'));

        $component->set('search', 'SEARCH');

        $this->assertCount(1, $component->get('students'));
        $this->assertEquals('SEARCH001', $component->get('students')[0]['student_id_number']);
    }

    public function test_search_filters_by_github_username(): void
    {
        $studentA = Student::factory()->create([
            'student_id_number' => 'CG008',
            'github_username' => 'findme-user',
        ]);
        $studentB = Student::factory()->create([
            'student_id_number' => 'CG009',
            'github_username' => 'hidden-user',
        ]);

        Enrollment::factory()->create([
            'student_id' => $studentA->id,
            'course_offering_id' => $this->offering->id,
        ]);

        Enrollment::factory()->create([
            'student_id' => $studentB->id,
            'course_offering_id' => $this->offering->id,
        ]);

        $component = Livewire::test(PublicClassGrades::class, ['token' => $this->offering->public_grade_token])
            ->set('search', 'findme');

        $this->assertCount(1, $component->get('students'));
        $this->assertEquals('findme-user', $component->get('students')[0]['github_username']);
    }

    public function test_ca_totals_compute_with_group_weights(): void
    {
        $scheme = GradingScheme::factory()->create();
        foreach ([
            ['letter' => 'A', 'min' => 80, 'max' => 100, 'points' => 4.0],
            ['letter' => 'B', 'min' => 60, 'max' => 79, 'points' => 3.0],
            ['letter' => 'C', 'min' => 40, 'max' => 59, 'points' => 2.0],
            ['letter' => 'D', 'min' => 0, 'max' => 39, 'points' => 1.0],
        ] as $lvl) {
            GradingSchemeLevel::factory()->create([
                'grading_scheme_id' => $scheme->id,
                'letter' => $lvl['letter'],
                'min_mark' => $lvl['min'],
                'max_mark' => $lvl['max'],
                'grade_points' => $lvl['points'],
            ]);
        }

        $offering = CourseOffering::factory()->withPublicGradeToken()->create([
            'ca_weight' => 40,
            'exam_weight' => 60,
            'grading_scheme_id' => $scheme->id,
        ]);

        $labs = AssessmentGroup::factory()->create([
            'course_offering_id' => $offering->id,
            'type' => 'ca',
            'name' => 'Labs',
            'weight_percentage' => 10,
        ]);
        $quizzes = AssessmentGroup::factory()->create([
            'course_offering_id' => $offering->id,
            'type' => 'ca',
            'name' => 'Quizzes',
            'weight_percentage' => 10,
        ]);

        $lab1 = Assessment::factory()->create([
            'assessment_group_id' => $labs->id,
            'course_id' => $offering->course_id,
            'max_raw_score' => 100,
        ]);
        $quiz1 = Assessment::factory()->create([
            'assessment_group_id' => $quizzes->id,
            'course_id' => $offering->course_id,
            'max_raw_score' => 30,
        ]);

        $student = Student::factory()->create(['student_id_number' => 'CGCA1']);
        $enrollment = Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $offering->id,
        ]);

        GradeResult::factory()->create([
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $lab1->id,
            'raw_score' => 80, // 80% of max 100
        ]);
        GradeResult::factory()->create([
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $quiz1->id,
            'raw_score' => 21, // 70% of max 30
        ]);

        $component = Livewire::test(PublicClassGrades::class, ['token' => $offering->public_grade_token])
            ->assertSet('step', 'loaded')
            ->assertSet('caWeight', 40.0);

        $row = $component->get('students')[0];
        // Labs group contributes 80% × 10 = 8 points; Quizzes 70% × 10 = 7 points.
        // CA total = 15 (out of 40). Out of 100 = 37.5 → letter D.
        $this->assertEquals(15.0, $row['ca_points']);
        $this->assertEquals(37.5, $row['ca_out_of_100']);
        $this->assertEquals('D', $row['ca_grade']);
    }

    public function test_search_shows_no_match_message(): void
    {
        $student = Student::factory()->create(['student_id_number' => 'CG010']);

        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
        ]);

        Livewire::test(PublicClassGrades::class, ['token' => $this->offering->public_grade_token])
            ->set('search', 'NONEXISTENT')
            ->assertSee('No students found matching');
    }
}
