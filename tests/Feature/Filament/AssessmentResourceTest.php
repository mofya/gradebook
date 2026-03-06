<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\AssessmentResource\Pages\CreateAssessment;
use App\Filament\Resources\AssessmentResource\Pages\EditAssessment;
use App\Filament\Resources\AssessmentResource\Pages\ListAssessments;
use App\Models\Assessment;
use App\Models\Course;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssessmentResourceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_list_page_renders(): void
    {
        $this->get(ListAssessments::getUrl())->assertSuccessful();
    }

    public function test_can_list_assessments(): void
    {
        $assessments = Assessment::factory()->count(3)->create();

        Livewire::test(ListAssessments::class)
            ->assertCanSeeTableRecords($assessments);
    }

    public function test_create_page_renders(): void
    {
        $this->get(CreateAssessment::getUrl())->assertSuccessful();
    }

    public function test_can_create_assessment(): void
    {
        $course = Course::factory()->create();

        Livewire::test(CreateAssessment::class)
            ->fillForm([
                'course_id' => $course->id,
                'name' => 'Midterm Exam',
                'weight' => 30,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('assessments', ['name' => 'Midterm Exam', 'weight' => 30]);
    }

    public function test_name_is_required(): void
    {
        Livewire::test(CreateAssessment::class)
            ->fillForm([
                'name' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['name' => 'required']);
    }

    public function test_course_is_required(): void
    {
        Livewire::test(CreateAssessment::class)
            ->fillForm([
                'course_id' => null,
            ])
            ->call('create')
            ->assertHasFormErrors(['course_id' => 'required']);
    }

    public function test_weight_is_required(): void
    {
        Livewire::test(CreateAssessment::class)
            ->fillForm([
                'weight' => '',
            ])
            ->call('create')
            ->assertHasFormErrors(['weight' => 'required']);
    }

    public function test_edit_page_renders(): void
    {
        $assessment = Assessment::factory()->create();

        $this->get(EditAssessment::getUrl(['record' => $assessment]))->assertSuccessful();
    }

    public function test_can_edit_assessment(): void
    {
        $assessment = Assessment::factory()->create();

        Livewire::test(EditAssessment::class, ['record' => $assessment->getRouteKey()])
            ->fillForm([
                'name' => 'Updated Assessment',
                'weight' => 50,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('assessments', ['id' => $assessment->id, 'name' => 'Updated Assessment']);
    }

    public function test_can_delete_assessment(): void
    {
        $assessment = Assessment::factory()->create();

        Livewire::test(ListAssessments::class)
            ->callTableAction('delete', $assessment);

        $this->assertDatabaseMissing('assessments', ['id' => $assessment->id]);
    }
}
