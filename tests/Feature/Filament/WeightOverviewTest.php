<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\CourseOfferingResource\Pages\WeightOverview;
use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\CourseOffering;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class WeightOverviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_weight_overview_page_renders(): void
    {
        $offering = CourseOffering::factory()->create();

        $this->get(WeightOverview::getUrl(['record' => $offering]))
            ->assertSuccessful();
    }

    public function test_weight_overview_shows_ca_and_exam_split(): void
    {
        $offering = CourseOffering::factory()->create([
            'ca_weight' => 60,
            'exam_weight' => 40,
        ]);

        Livewire::test(WeightOverview::class, ['record' => $offering->getRouteKey()])
            ->assertSee('CA: 60%')
            ->assertSee('Exam: 40%');
    }

    public function test_weight_overview_shows_assessment_groups(): void
    {
        $offering = CourseOffering::factory()->create([
            'ca_weight' => 50,
            'exam_weight' => 50,
        ]);

        $caGroup = AssessmentGroup::factory()->create([
            'course_offering_id' => $offering->id,
            'name' => 'Quizzes',
            'type' => 'ca',
            'weight_percentage' => 100,
        ]);

        Assessment::factory()->create([
            'assessment_group_id' => $caGroup->id,
            'course_id' => $offering->course_id,
            'name' => 'Quiz 1',
            'weight' => 50,
        ]);

        Assessment::factory()->create([
            'assessment_group_id' => $caGroup->id,
            'course_id' => $offering->course_id,
            'name' => 'Quiz 2',
            'weight' => 50,
        ]);

        Livewire::test(WeightOverview::class, ['record' => $offering->getRouteKey()])
            ->assertSee('Quizzes')
            ->assertSee('Quiz 1')
            ->assertSee('Quiz 2');
    }

    public function test_weight_overview_calculates_effective_percentages(): void
    {
        $offering = CourseOffering::factory()->create([
            'ca_weight' => 50,
            'exam_weight' => 50,
        ]);

        $caGroup = AssessmentGroup::factory()->create([
            'course_offering_id' => $offering->id,
            'name' => 'Assignments',
            'type' => 'ca',
            'weight_percentage' => 100,
        ]);

        Assessment::factory()->create([
            'assessment_group_id' => $caGroup->id,
            'course_id' => $offering->course_id,
            'name' => 'Assignment 1',
            'weight' => 30,
        ]);

        Assessment::factory()->create([
            'assessment_group_id' => $caGroup->id,
            'course_id' => $offering->course_id,
            'name' => 'Assignment 2',
            'weight' => 70,
        ]);

        // Assignment 1: 50% (ca) * 100% (group) * 30% (weight) / 10000 = 15%
        // Assignment 2: 50% (ca) * 100% (group) * 70% (weight) / 10000 = 35%
        Livewire::test(WeightOverview::class, ['record' => $offering->getRouteKey()])
            ->assertSee('15.00%')
            ->assertSee('35.00%');
    }

    public function test_weight_overview_shows_validation_for_valid_weights(): void
    {
        $offering = CourseOffering::factory()->create([
            'ca_weight' => 50,
            'exam_weight' => 50,
        ]);

        $caGroup = AssessmentGroup::factory()->create([
            'course_offering_id' => $offering->id,
            'name' => 'CA Group',
            'type' => 'ca',
            'weight_percentage' => 100,
        ]);

        Assessment::factory()->create([
            'assessment_group_id' => $caGroup->id,
            'course_id' => $offering->course_id,
            'weight' => 100,
        ]);

        $examGroup = AssessmentGroup::factory()->create([
            'course_offering_id' => $offering->id,
            'name' => 'Exam Group',
            'type' => 'exam',
            'weight_percentage' => 100,
        ]);

        Assessment::factory()->create([
            'assessment_group_id' => $examGroup->id,
            'course_id' => $offering->course_id,
            'weight' => 100,
        ]);

        // Grand total = 50 + 50 = 100
        Livewire::test(WeightOverview::class, ['record' => $offering->getRouteKey()])
            ->assertSee('100.00%');
    }

    public function test_weight_overview_shows_empty_state(): void
    {
        $offering = CourseOffering::factory()->create();

        Livewire::test(WeightOverview::class, ['record' => $offering->getRouteKey()])
            ->assertSee('No assessment groups configured');
    }
}
