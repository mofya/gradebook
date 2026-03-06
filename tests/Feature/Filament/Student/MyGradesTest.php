<?php

namespace Tests\Feature\Filament\Student;

use App\Filament\Student\Pages\MyGrades;
use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MyGradesTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->student()->create();
        $this->student = Student::factory()->create(['email' => $this->user->email]);
        $this->actingAs($this->user);
    }

    public function test_my_grades_page_renders(): void
    {
        Livewire::test(MyGrades::class)
            ->assertSuccessful()
            ->assertSee('No enrollments found');
    }

    public function test_my_grades_shows_enrollment_data(): void
    {
        $year = Year::factory()->create();
        $semester = Semester::factory()->create(['year_id' => $year->id]);
        $course = Course::factory()->create(['year_id' => $year->id, 'credits' => 3]);
        $offering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
        ]);

        Enrollment::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $offering->id,
            'ca_total' => 72.00,
            'status' => 'enrolled',
        ]);

        Livewire::test(MyGrades::class)
            ->assertSee($course->code)
            ->assertSee($course->name)
            ->assertSee('Enrolled');
    }

    public function test_my_grades_shows_ca_total(): void
    {
        $year = Year::factory()->create();
        $semester = Semester::factory()->create(['year_id' => $year->id]);
        $course = Course::factory()->create(['year_id' => $year->id, 'credits' => 3]);
        $offering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'ca_weight' => 40,
        ]);

        $group = AssessmentGroup::factory()->create([
            'course_offering_id' => $offering->id,
            'type' => 'ca',
        ]);

        Assessment::factory()->create([
            'course_id' => $course->id,
            'assessment_group_id' => $group->id,
            'name' => 'Quiz 1',
            'max_raw_score' => 100,
        ]);

        Enrollment::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $offering->id,
            'ca_total' => 80.00,
        ]);

        Livewire::test(MyGrades::class)
            ->assertSee('CA Total')
            ->assertSee('32.0'); // 80 * 40/100 = 32.0
    }

    public function test_my_grades_shows_no_assessments_message(): void
    {
        $year = Year::factory()->create();
        $semester = Semester::factory()->create(['year_id' => $year->id]);
        $course = Course::factory()->create(['year_id' => $year->id, 'credits' => 3]);
        $offering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
        ]);

        Enrollment::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $offering->id,
        ]);

        Livewire::test(MyGrades::class)
            ->assertSee('No assessments set up yet');
    }

    public function test_my_grades_shows_no_student_message_for_unknown_user(): void
    {
        $otherUser = User::factory()->student()->create();
        $this->actingAs($otherUser);

        Livewire::test(MyGrades::class)
            ->assertSee('No student record found');
    }
}
