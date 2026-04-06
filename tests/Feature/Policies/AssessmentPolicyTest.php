<?php

namespace Tests\Feature\Policies;

use App\Models\Assessment;
use App\Models\AssessmentGroup;
use App\Models\CourseOffering;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_any_assessment(): void
    {
        $admin = User::factory()->admin()->create();
        $offering = CourseOffering::factory()->create();
        $group = AssessmentGroup::factory()->create(['course_offering_id' => $offering->id]);
        $assessment = Assessment::factory()->create([
            'assessment_group_id' => $group->id,
            'course_id' => $offering->course_id,
        ]);

        $this->assertTrue($admin->can('view', $assessment));
    }

    public function test_lecturer_can_view_assessment_in_own_offering(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $offering = CourseOffering::factory()->create(['lecturer_id' => $lecturer->id]);
        $group = AssessmentGroup::factory()->create(['course_offering_id' => $offering->id]);
        $assessment = Assessment::factory()->create([
            'assessment_group_id' => $group->id,
            'course_id' => $offering->course_id,
        ]);

        $this->assertTrue($lecturer->can('view', $assessment));
    }

    public function test_lecturer_cannot_view_assessment_in_another_lecturers_offering(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $otherLecturer = User::factory()->lecturer()->create();
        $offering = CourseOffering::factory()->create(['lecturer_id' => $otherLecturer->id]);
        $group = AssessmentGroup::factory()->create(['course_offering_id' => $offering->id]);
        $assessment = Assessment::factory()->create([
            'assessment_group_id' => $group->id,
            'course_id' => $offering->course_id,
        ]);

        $this->assertFalse($lecturer->can('view', $assessment));
    }

    public function test_lecturer_can_update_assessment_in_own_offering(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $offering = CourseOffering::factory()->create(['lecturer_id' => $lecturer->id]);
        $group = AssessmentGroup::factory()->create(['course_offering_id' => $offering->id]);
        $assessment = Assessment::factory()->create([
            'assessment_group_id' => $group->id,
            'course_id' => $offering->course_id,
        ]);

        $this->assertTrue($lecturer->can('update', $assessment));
    }

    public function test_lecturer_cannot_update_assessment_in_another_lecturers_offering(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $otherLecturer = User::factory()->lecturer()->create();
        $offering = CourseOffering::factory()->create(['lecturer_id' => $otherLecturer->id]);
        $group = AssessmentGroup::factory()->create(['course_offering_id' => $offering->id]);
        $assessment = Assessment::factory()->create([
            'assessment_group_id' => $group->id,
            'course_id' => $offering->course_id,
        ]);

        $this->assertFalse($lecturer->can('update', $assessment));
    }

    public function test_lecturer_cannot_delete_assessment(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $offering = CourseOffering::factory()->create(['lecturer_id' => $lecturer->id]);
        $group = AssessmentGroup::factory()->create(['course_offering_id' => $offering->id]);
        $assessment = Assessment::factory()->create([
            'assessment_group_id' => $group->id,
            'course_id' => $offering->course_id,
        ]);

        $this->assertFalse($lecturer->can('delete', $assessment));
    }
}
