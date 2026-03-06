<?php

namespace Tests\Feature\Filament;

use App\Filament\Resources\CourseResource\Pages\AssessmentWeights;
use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AssessmentWeightsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_assessment_weights_page_renders(): void
    {
        $course = Course::factory()->create();

        $this->get(AssessmentWeights::getUrl(['record' => $course]))
            ->assertSuccessful();
    }

    public function test_shows_empty_state_when_no_offerings(): void
    {
        $course = Course::factory()->create();

        Livewire::test(AssessmentWeights::class, ['record' => $course->getRouteKey()])
            ->assertSee('No course offerings exist');
    }

    public function test_defaults_to_most_recent_offering(): void
    {
        $course = Course::factory()->create();

        $older = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'created_at' => now()->subMonth(),
        ]);

        $newer = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'created_at' => now(),
        ]);

        Livewire::test(AssessmentWeights::class, ['record' => $course->getRouteKey()])
            ->assertSet('selectedOfferingId', $newer->id);
    }

    public function test_can_switch_offering(): void
    {
        $course = Course::factory()->create();

        $offering1 = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'ca_weight' => 60,
            'exam_weight' => 40,
            'created_at' => now()->subMonth(),
        ]);

        $offering2 = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'ca_weight' => 40,
            'exam_weight' => 60,
            'created_at' => now(),
        ]);

        Livewire::test(AssessmentWeights::class, ['record' => $course->getRouteKey()])
            ->assertSee('CA: 40%')
            ->set('selectedOfferingId', $offering1->id)
            ->assertSee('CA: 60%');
    }

    public function test_shows_weight_hierarchy(): void
    {
        $course = Course::factory()->create();

        $offering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'ca_weight' => 50,
            'exam_weight' => 50,
        ]);

        $group = AssessmentGroup::factory()->create([
            'course_offering_id' => $offering->id,
            'name' => 'Homework',
            'type' => 'ca',
            'weight_percentage' => 100,
        ]);

        Assessment::factory()->create([
            'assessment_group_id' => $group->id,
            'course_id' => $course->id,
            'name' => 'HW 1',
            'weight' => 100,
        ]);

        Livewire::test(AssessmentWeights::class, ['record' => $course->getRouteKey()])
            ->assertSee('Homework')
            ->assertSee('HW 1')
            ->assertSee('50.00%');
    }
}
