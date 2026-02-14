<?php

namespace Tests\Feature\Models;

use App\Enums\OfferingStatus;
use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseOfferingLifecycleTest extends TestCase
{
    use RefreshDatabase;

    private function createOffering(OfferingStatus $status = OfferingStatus::Draft, bool $withAssessments = true): CourseOffering
    {
        $year = Year::factory()->create();
        $semester = Semester::factory()->create(['year_id' => $year->id]);
        $course = Course::factory()->create(['year_id' => $year->id]);

        $offering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'ca_weight' => 50,
            'exam_weight' => 50,
            'status' => $status,
        ]);

        if ($withAssessments) {
            $group = AssessmentGroup::factory()->create([
                'course_offering_id' => $offering->id,
                'type' => 'ca',
            ]);
            Assessment::factory()->create([
                'assessment_group_id' => $group->id,
                'course_id' => $course->id,
            ]);
        }

        return $offering;
    }

    public function test_activate_transitions_draft_to_active(): void
    {
        $offering = $this->createOffering();

        $offering->activate();

        $this->assertEquals(OfferingStatus::Active, $offering->fresh()->status);
    }

    public function test_activate_fails_if_not_draft(): void
    {
        $offering = $this->createOffering(OfferingStatus::Active);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Only draft offerings can be activated.');

        $offering->activate();
    }

    public function test_activate_fails_without_assessment_groups(): void
    {
        $offering = $this->createOffering(withAssessments: false);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('At least one assessment group is required');

        $offering->activate();
    }

    public function test_activate_fails_with_invalid_weights(): void
    {
        $offering = $this->createOffering();
        $offering->update(['ca_weight' => 30, 'exam_weight' => 30]);

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('CA and exam weights must sum to 100.');

        $offering->activate();
    }

    public function test_lock_transitions_active_to_locked(): void
    {
        $offering = $this->createOffering(OfferingStatus::Active);

        $offering->lock();

        $this->assertEquals(OfferingStatus::Locked, $offering->fresh()->status);
    }

    public function test_lock_fails_if_not_active(): void
    {
        $offering = $this->createOffering(OfferingStatus::Draft);

        $this->expectException(\LogicException::class);

        $offering->lock();
    }

    public function test_publish_transitions_locked_to_published(): void
    {
        $offering = $this->createOffering(OfferingStatus::Locked);

        $offering->publish();

        $fresh = $offering->fresh();
        $this->assertEquals(OfferingStatus::Published, $fresh->status);
        $this->assertTrue($fresh->is_published);
        $this->assertNotNull($fresh->published_at);
    }

    public function test_publish_fails_if_not_locked(): void
    {
        $offering = $this->createOffering(OfferingStatus::Active);

        $this->expectException(\LogicException::class);

        $offering->publish();
    }

    public function test_duplicate_creates_draft_copy_with_assessments(): void
    {
        $offering = $this->createOffering();
        $offering->load('assessmentGroups.assessments');

        $copy = $offering->duplicate();

        $this->assertEquals(OfferingStatus::Draft, $copy->status);
        $this->assertEquals($offering->id, $copy->created_from_offering_id);
        $this->assertFalse($copy->is_published);
        $this->assertEquals($offering->course_id, $copy->course_id);
        $this->assertEquals($offering->ca_weight, $copy->ca_weight);

        // Should have copied assessment groups and assessments
        $this->assertEquals(
            $offering->assessmentGroups->count(),
            $copy->assessmentGroups()->count()
        );

        $originalAssessmentCount = $offering->assessmentGroups->flatMap->assessments->count();
        $copiedAssessmentCount = $copy->assessmentGroups()->with('assessments')->get()->flatMap->assessments->count();
        $this->assertEquals($originalAssessmentCount, $copiedAssessmentCount);
    }

    public function test_duplicate_to_different_semester(): void
    {
        $offering = $this->createOffering();
        $newYear = Year::factory()->create();
        $newSemester = Semester::factory()->create(['year_id' => $newYear->id]);

        $copy = $offering->duplicate($newSemester);

        $this->assertEquals($newSemester->id, $copy->semester_id);
        $this->assertEquals($offering->id, $copy->created_from_offering_id);
    }
}
