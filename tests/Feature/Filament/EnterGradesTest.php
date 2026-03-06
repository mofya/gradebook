<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\CourseResource\Pages\EnterGrades;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EnterGradesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_page_renders(): void
    {
        $course = Course::factory()->create();

        $this->get(EnterGrades::getUrl(['record' => $course]))->assertSuccessful();
    }

    public function test_can_load_grades(): void
    {
        $course = Course::factory()->create();
        $assessment = Assessment::factory()->create(['course_id' => $course->id]);
        $student = Student::factory()->create();
        $course->students()->attach($student);

        $component = Livewire::test(EnterGrades::class, ['record' => $course->getRouteKey()])
            ->set('assessment_id', $assessment->id)
            ->call('loadGrades');

        $component->assertSet("grades.{$student->id}.student_name", $student->first_name.' '.$student->last_name);
    }

    public function test_can_submit_grades(): void
    {
        $course = Course::factory()->create();
        $assessment = Assessment::factory()->create(['course_id' => $course->id]);
        $student = Student::factory()->create();
        $course->students()->attach($student);

        Livewire::test(EnterGrades::class, ['record' => $course->getRouteKey()])
            ->set('assessment_id', $assessment->id)
            ->call('loadGrades')
            ->set("grades.{$student->id}.grade", 85)
            ->call('submit');

        $this->assertDatabaseHas('grades', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'assessment_id' => $assessment->id,
            'grade' => 85,
        ]);
    }

    public function test_can_update_existing_grades(): void
    {
        $course = Course::factory()->create();
        $assessment = Assessment::factory()->create(['course_id' => $course->id]);
        $student = Student::factory()->create();
        $course->students()->attach($student);

        Grade::factory()->create([
            'student_id' => $student->id,
            'course_id' => $course->id,
            'assessment_id' => $assessment->id,
            'grade' => 70,
        ]);

        Livewire::test(EnterGrades::class, ['record' => $course->getRouteKey()])
            ->set('assessment_id', $assessment->id)
            ->call('loadGrades')
            ->set("grades.{$student->id}.grade", 90)
            ->call('submit');

        $this->assertDatabaseHas('grades', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'assessment_id' => $assessment->id,
            'grade' => 90,
        ]);

        $this->assertDatabaseCount('grades', 1);
    }
}
