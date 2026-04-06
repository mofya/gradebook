<?php

namespace Tests\Feature;

use App\Livewire\PublicGrades;
use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeResult;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PublicGradesTest extends TestCase
{
    use RefreshDatabase;

    private CourseOffering $offering;

    protected function setUp(): void
    {
        parent::setUp();

        $year = Year::factory()->create();
        $semester = Semester::factory()->create(['year_id' => $year->id]);
        $course = Course::factory()->create(['year_id' => $year->id]);

        $this->offering = CourseOffering::factory()->withVerificationToken()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
        ]);
    }

    public function test_grades_page_loads_with_valid_token(): void
    {
        $response = $this->get(route('student.grades', ['token' => $this->offering->verification_token]));

        $response->assertOk();
        $response->assertSee($this->offering->course->code);
        $response->assertSee('View Your Grades');
    }

    public function test_expired_token_shows_expired_message(): void
    {
        $offering = CourseOffering::factory()->withVerificationToken(-1)->create();

        Livewire::test(PublicGrades::class, ['token' => $offering->verification_token])
            ->assertSet('step', 'expired')
            ->assertSee('Link Expired');
    }

    public function test_invalid_token_shows_expired(): void
    {
        Livewire::test(PublicGrades::class, ['token' => 'nonexistent-token-xyz'])
            ->assertSet('step', 'expired');
    }

    public function test_student_lookup_finds_enrolled_student(): void
    {
        $student = Student::factory()->create(['student_id_number' => 'SNG001']);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
        ]);

        Livewire::test(PublicGrades::class, ['token' => $this->offering->verification_token])
            ->set('studentIdNumber', 'SNG001')
            ->call('viewGrades')
            ->assertSet('step', 'found')
            ->assertSet('studentName', $student->first_name.' '.$student->last_name);
    }

    public function test_student_lookup_fails_for_unenrolled_student(): void
    {
        Student::factory()->create(['student_id_number' => 'SNG002']);

        Livewire::test(PublicGrades::class, ['token' => $this->offering->verification_token])
            ->set('studentIdNumber', 'SNG002')
            ->call('viewGrades')
            ->assertSet('step', 'not_found');
    }

    public function test_student_lookup_fails_for_nonexistent_student(): void
    {
        Livewire::test(PublicGrades::class, ['token' => $this->offering->verification_token])
            ->set('studentIdNumber', 'DOESNOTEXIST')
            ->call('viewGrades')
            ->assertSet('step', 'not_found');
    }

    public function test_grades_display_correct_values(): void
    {
        $student = Student::factory()->create(['student_id_number' => 'SNG003']);
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
            'max_raw_score' => 100,
        ]);

        GradeResult::factory()->create([
            'enrollment_id' => $enrollment->id,
            'assessment_id' => $assessment->id,
            'raw_score' => 85,
        ]);

        Livewire::test(PublicGrades::class, ['token' => $this->offering->verification_token])
            ->set('studentIdNumber', 'SNG003')
            ->call('viewGrades')
            ->assertSet('step', 'found')
            ->assertSee('Labs')
            ->assertSee('Lab 1')
            ->assertSee('85.0');
    }

    public function test_exam_assessments_are_excluded(): void
    {
        $student = Student::factory()->create(['student_id_number' => 'SNG004']);
        $enrollment = Enrollment::factory()->create([
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
            'max_raw_score' => 100,
        ]);

        Livewire::test(PublicGrades::class, ['token' => $this->offering->verification_token])
            ->set('studentIdNumber', 'SNG004')
            ->call('viewGrades')
            ->assertSet('step', 'found')
            ->assertDontSee('Final Exam')
            ->assertDontSee('Exam Paper');
    }

    public function test_reset_lookup_returns_to_lookup_step(): void
    {
        $student = Student::factory()->create(['student_id_number' => 'SNG005']);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
        ]);

        Livewire::test(PublicGrades::class, ['token' => $this->offering->verification_token])
            ->set('studentIdNumber', 'SNG005')
            ->call('viewGrades')
            ->assertSet('step', 'found')
            ->call('resetLookup')
            ->assertSet('step', 'lookup')
            ->assertSet('studentIdNumber', '');
    }
}
