<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\CourseResource\Pages\CreateCourse;
use App\Filament\Resources\CourseResource\Pages\EditCourse;
use App\Filament\Resources\CourseResource\Pages\ListCourses;
use App\Models\Course;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CourseResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_list_page_renders(): void
    {
        $this->get(ListCourses::getUrl())->assertSuccessful();
    }

    public function test_can_list_courses(): void
    {
        $courses = Course::factory()->count(3)->create();

        Livewire::test(ListCourses::class)
            ->assertCanSeeTableRecords($courses);
    }

    public function test_create_page_renders(): void
    {
        $this->get(CreateCourse::getUrl())->assertSuccessful();
    }

    public function test_can_create_course(): void
    {
        $year = Year::factory()->create();

        Livewire::test(CreateCourse::class)
            ->fillForm([
                'name' => 'Mathematics 101',
                'code' => 'MATH-101',
                'year_id' => $year->id,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('courses', ['name' => 'Mathematics 101', 'code' => 'MATH-101']);
    }

    public function test_name_is_required(): void
    {
        Livewire::test(CreateCourse::class)
            ->fillForm([
                'name' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    }

    public function test_code_is_required(): void
    {
        Livewire::test(CreateCourse::class)
            ->fillForm([
                'code' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['code' => 'required']);
    }

    public function test_code_must_be_unique(): void
    {
        Course::factory()->create(['code' => 'MATH-101']);
        $year = Year::factory()->create();

        Livewire::test(CreateCourse::class)
            ->fillForm([
                'name' => 'Another Math',
                'code' => 'MATH-101',
                'year_id' => $year->id,
            ])
            ->call('create')
            ->assertHasFormErrors(['code' => 'unique']);
    }

    public function test_year_is_required(): void
    {
        Livewire::test(CreateCourse::class)
            ->fillForm([
                'year_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['year_id' => 'required']);
    }

    public function test_edit_page_renders(): void
    {
        $course = Course::factory()->create();

        $this->get(EditCourse::getUrl(['record' => $course]))->assertSuccessful();
    }

    public function test_can_edit_course(): void
    {
        $course = Course::factory()->create();

        Livewire::test(EditCourse::class, ['record' => $course->getRouteKey()])
            ->fillForm([
                'name' => 'Updated Course',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('courses', ['id' => $course->id, 'name' => 'Updated Course']);
    }

    public function test_can_delete_course(): void
    {
        $course = Course::factory()->create();

        Livewire::test(EditCourse::class, ['record' => $course->getRouteKey()])
            ->callAction('delete');

        $this->assertDatabaseMissing('courses', ['id' => $course->id]);
    }
}
