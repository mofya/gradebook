<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\CourseResource\Pages\EditCourse;
use App\Filament\Resources\CourseResource\RelationManagers\StudentsRelationManager;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class StudentsRelationManagerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_can_render_relation_manager(): void
    {
        $course = Course::factory()->create();

        Livewire::test(StudentsRelationManager::class, [
            'ownerRecord' => $course,
            'pageClass' => EditCourse::class,
        ])->assertSuccessful();
    }

    public function test_can_list_enrollments(): void
    {
        $course = Course::factory()->create();
        $offering = CourseOffering::factory()->create(['course_id' => $course->id]);
        $enrollments = Enrollment::factory()->count(3)->create([
            'course_offering_id' => $offering->id,
        ]);

        Livewire::test(StudentsRelationManager::class, [
            'ownerRecord' => $course,
            'pageClass' => EditCourse::class,
        ])->assertCanSeeTableRecords($enrollments);
    }

    public function test_displays_student_info_columns(): void
    {
        $course = Course::factory()->create();
        $offering = CourseOffering::factory()->create(['course_id' => $course->id]);
        Enrollment::factory()->create([
            'course_offering_id' => $offering->id,
            'status' => 'enrolled',
        ]);

        Livewire::test(StudentsRelationManager::class, [
            'ownerRecord' => $course,
            'pageClass' => EditCourse::class,
        ])
            ->assertSuccessful()
            ->assertCanRenderTableColumn('student.student_id_number')
            ->assertCanRenderTableColumn('student.last_name')
            ->assertCanRenderTableColumn('student.first_name')
            ->assertCanRenderTableColumn('status');
    }

    public function test_displays_grade_columns(): void
    {
        $course = Course::factory()->create();
        $offering = CourseOffering::factory()->create(['course_id' => $course->id]);
        Enrollment::factory()->create([
            'course_offering_id' => $offering->id,
            'ca_total' => 45.50,
            'exam_score' => 60.00,
            'final_total' => 72.75,
            'final_grade' => 'B+',
            'grade_points' => 3.5,
        ]);

        Livewire::test(StudentsRelationManager::class, [
            'ownerRecord' => $course,
            'pageClass' => EditCourse::class,
        ])
            ->assertSuccessful()
            ->assertCanRenderTableColumn('ca_total')
            ->assertCanRenderTableColumn('exam_score')
            ->assertCanRenderTableColumn('final_total')
            ->assertCanRenderTableColumn('final_grade')
            ->assertCanRenderTableColumn('grade_points');
    }
}
