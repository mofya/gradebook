<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\StudentResource\Pages\EditStudent;
use App\Filament\Resources\StudentResource\RelationManagers\CoursesRelationManager;
use App\Models\Course;
use App\Models\Student;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CoursesRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_can_render_relation_manager(): void
    {
        $student = Student::factory()->create();

        Livewire::test(CoursesRelationManager::class, [
            'ownerRecord' => $student,
            'pageClass' => EditStudent::class,
        ])->assertSuccessful();
    }

    public function test_can_list_courses(): void
    {
        $student = Student::factory()->create();
        $courses = Course::factory()->count(3)->create();
        $student->courses()->attach($courses);

        Livewire::test(CoursesRelationManager::class, [
            'ownerRecord' => $student,
            'pageClass' => EditStudent::class,
        ])->assertCanSeeTableRecords($courses);
    }

    public function test_can_create_course(): void
    {
        $student = Student::factory()->create();
        $year = Year::factory()->create();

        Livewire::test(CoursesRelationManager::class, [
            'ownerRecord' => $student,
            'pageClass' => EditStudent::class,
        ])
            ->callTableAction('create', data: [
                'name' => 'Physics 101',
                'code' => 'PHY-101',
                'year_id' => $year->id,
            ]);

        $this->assertDatabaseHas('courses', ['name' => 'Physics 101', 'code' => 'PHY-101']);
    }

    public function test_can_attach_course(): void
    {
        $student = Student::factory()->create();
        $course = Course::factory()->create();

        Livewire::test(CoursesRelationManager::class, [
            'ownerRecord' => $student,
            'pageClass' => EditStudent::class,
        ])
            ->callTableAction('attach', data: [
                'recordId' => $course->id,
            ]);

        $this->assertTrue($student->courses()->where('courses.id', $course->id)->exists());
    }

    public function test_can_detach_course(): void
    {
        $student = Student::factory()->create();
        $course = Course::factory()->create();
        $student->courses()->attach($course);

        Livewire::test(CoursesRelationManager::class, [
            'ownerRecord' => $student,
            'pageClass' => EditStudent::class,
        ])
            ->callTableAction('detach', $course);

        $this->assertFalse($student->courses()->where('courses.id', $course->id)->exists());
    }
}
