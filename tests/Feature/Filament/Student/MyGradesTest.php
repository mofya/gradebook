<?php

namespace Tests\Feature\Filament\Student;

use App\Filament\Student\Pages\MyGrades;
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
            ->assertSee('Academic Summary');
    }

    public function test_my_grades_shows_enrollment_data_when_published(): void
    {
        $year = Year::factory()->create();
        $semester = Semester::factory()->create(['year_id' => $year->id]);
        $course = Course::factory()->create(['year_id' => $year->id, 'credits' => 3]);
        $offering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'is_published' => true,
        ]);

        Enrollment::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $offering->id,
            'ca_total' => 35.00,
            'exam_score' => 60.00,
            'final_total' => 75.00,
            'final_grade' => 'B+',
            'grade_points' => 3.5,
        ]);

        Livewire::test(MyGrades::class)
            ->assertSee($course->code)
            ->assertSee('B+');
    }

    public function test_my_grades_hides_grades_when_unpublished(): void
    {
        $year = Year::factory()->create();
        $semester = Semester::factory()->create(['year_id' => $year->id]);
        $course = Course::factory()->create(['year_id' => $year->id, 'credits' => 3]);
        $offering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'is_published' => false,
        ]);

        Enrollment::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $offering->id,
            'final_total' => 75.00,
            'final_grade' => 'B+',
            'grade_points' => 3.5,
        ]);

        Livewire::test(MyGrades::class)
            ->assertSee($course->code)
            ->assertSee('Grades not yet published')
            ->assertDontSee('B+');
    }

    public function test_my_grades_shows_cgpa(): void
    {
        $year = Year::factory()->create();
        $semester = Semester::factory()->create(['year_id' => $year->id]);
        $course = Course::factory()->create(['year_id' => $year->id, 'credits' => 3]);
        $offering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'is_published' => true,
        ]);

        Enrollment::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $offering->id,
            'final_total' => 85.00,
            'final_grade' => 'A',
        ]);

        Livewire::test(MyGrades::class)
            ->assertSee('CGPA');
    }

    public function test_my_grades_shows_no_student_message_for_unknown_user(): void
    {
        $otherUser = User::factory()->student()->create();
        $this->actingAs($otherUser);

        Livewire::test(MyGrades::class)
            ->assertSee('No student record found');
    }
}
