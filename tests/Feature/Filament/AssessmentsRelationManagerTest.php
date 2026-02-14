<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\CourseResource\Pages\EditCourse;
use App\Filament\Resources\CourseResource\RelationManagers\AssessmentsRelationManager;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssessmentsRelationManagerTest extends TestCase
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

        Livewire::test(AssessmentsRelationManager::class, [
            'ownerRecord' => $course,
            'pageClass' => EditCourse::class,
        ])->assertSuccessful();
    }

    public function test_can_list_assessments(): void
    {
        $course = Course::factory()->create();
        $assessments = Assessment::factory()->count(3)->create(['course_id' => $course->id]);

        Livewire::test(AssessmentsRelationManager::class, [
            'ownerRecord' => $course,
            'pageClass' => EditCourse::class,
        ])->assertCanSeeTableRecords($assessments);
    }

    public function test_can_create_assessment(): void
    {
        $course = Course::factory()->create();

        Livewire::test(AssessmentsRelationManager::class, [
            'ownerRecord' => $course,
            'pageClass' => EditCourse::class,
        ])
            ->callTableAction('create', data: [
                'name' => 'Final Exam',
                'weight' => 40,
            ]);

        $this->assertDatabaseHas('assessments', [
            'course_id' => $course->id,
            'name' => 'Final Exam',
            'weight' => 40,
        ]);
    }

    public function test_can_edit_assessment(): void
    {
        $course = Course::factory()->create();
        $assessment = Assessment::factory()->create(['course_id' => $course->id]);

        Livewire::test(AssessmentsRelationManager::class, [
            'ownerRecord' => $course,
            'pageClass' => EditCourse::class,
        ])
            ->callTableAction('edit', $assessment, data: [
                'name' => 'Updated Exam',
                'weight' => 50,
            ]);

        $this->assertDatabaseHas('assessments', [
            'id' => $assessment->id,
            'name' => 'Updated Exam',
        ]);
    }

    public function test_can_delete_assessment(): void
    {
        $course = Course::factory()->create();
        $assessment = Assessment::factory()->create(['course_id' => $course->id]);

        Livewire::test(AssessmentsRelationManager::class, [
            'ownerRecord' => $course,
            'pageClass' => EditCourse::class,
        ])
            ->callTableAction('delete', $assessment);

        $this->assertDatabaseMissing('assessments', ['id' => $assessment->id]);
    }
}
