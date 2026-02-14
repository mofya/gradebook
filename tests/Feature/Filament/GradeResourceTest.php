<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\GradeResource\Pages\CreateGrade;
use App\Filament\Resources\GradeResource\Pages\EditGrade;
use App\Filament\Resources\GradeResource\Pages\ListGrades;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\Grade;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GradeResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_list_page_renders(): void
    {
        $this->get(ListGrades::getUrl())->assertSuccessful();
    }

    public function test_can_list_grades(): void
    {
        $course = Course::factory()->create();
        $assessment = Assessment::factory()->create(['course_id' => $course->id]);
        $grades = Grade::factory()->count(3)->create([
            'course_id' => $course->id,
            'assessment_id' => $assessment->id,
        ]);

        Livewire::test(ListGrades::class)
            ->assertCanSeeTableRecords($grades);
    }

    public function test_create_page_renders(): void
    {
        $this->get(CreateGrade::getUrl())->assertSuccessful();
    }

    public function test_can_create_grade(): void
    {
        $student = Student::factory()->create();
        $course = Course::factory()->create();
        $assessment = Assessment::factory()->create(['course_id' => $course->id]);

        Livewire::test(CreateGrade::class)
            ->fillForm([
                'student_id' => $student->id,
                'course_id' => $course->id,
                'assessment_id' => $assessment->id,
                'grade' => 85,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('grades', [
            'student_id' => $student->id,
            'course_id' => $course->id,
            'assessment_id' => $assessment->id,
            'grade' => 85,
        ]);
    }

    public function test_student_is_required(): void
    {
        Livewire::test(CreateGrade::class)
            ->fillForm([
                'student_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['student_id' => 'required']);
    }

    public function test_course_is_required(): void
    {
        Livewire::test(CreateGrade::class)
            ->fillForm([
                'course_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['course_id' => 'required']);
    }

    public function test_assessment_is_required(): void
    {
        Livewire::test(CreateGrade::class)
            ->fillForm([
                'assessment_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['assessment_id' => 'required']);
    }

    public function test_grade_is_required(): void
    {
        Livewire::test(CreateGrade::class)
            ->fillForm([
                'grade' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['grade' => 'required']);
    }

    public function test_edit_page_renders(): void
    {
        $course = Course::factory()->create();
        $grade = Grade::factory()->create([
            'course_id' => $course->id,
            'assessment_id' => Assessment::factory()->create(['course_id' => $course->id])->id,
        ]);

        $this->get(EditGrade::getUrl(['record' => $grade]))->assertSuccessful();
    }

    public function test_can_edit_grade(): void
    {
        $course = Course::factory()->create();
        $grade = Grade::factory()->create([
            'course_id' => $course->id,
            'assessment_id' => Assessment::factory()->create(['course_id' => $course->id])->id,
            'grade' => 70,
        ]);

        Livewire::test(EditGrade::class, ['record' => $grade->getRouteKey()])
            ->fillForm([
                'grade' => 95,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('grades', ['id' => $grade->id, 'grade' => 95]);
    }

    public function test_can_delete_grade(): void
    {
        $course = Course::factory()->create();
        $grade = Grade::factory()->create([
            'course_id' => $course->id,
            'assessment_id' => Assessment::factory()->create(['course_id' => $course->id])->id,
        ]);

        Livewire::test(ListGrades::class)
            ->callTableAction('delete', $grade);

        $this->assertDatabaseMissing('grades', ['id' => $grade->id]);
    }
}
