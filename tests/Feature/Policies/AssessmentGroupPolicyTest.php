<?php

namespace Tests\Feature\Policies;

use App\Models\AssessmentGroup;
use App\Models\CourseOffering;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssessmentGroupPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_any_assessment_group(): void
    {
        $admin = User::factory()->admin()->create();
        $group = AssessmentGroup::factory()->create();

        $this->assertTrue($admin->can('view', $group));
    }

    public function test_lecturer_can_view_assessment_group_in_own_offering(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $offering = CourseOffering::factory()->create(['lecturer_id' => $lecturer->id]);
        $group = AssessmentGroup::factory()->create(['course_offering_id' => $offering->id]);

        $this->assertTrue($lecturer->can('view', $group));
    }

    public function test_lecturer_cannot_view_assessment_group_in_another_lecturers_offering(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $otherLecturer = User::factory()->lecturer()->create();
        $offering = CourseOffering::factory()->create(['lecturer_id' => $otherLecturer->id]);
        $group = AssessmentGroup::factory()->create(['course_offering_id' => $offering->id]);

        $this->assertFalse($lecturer->can('view', $group));
    }

    public function test_lecturer_can_update_assessment_group_in_own_offering(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $offering = CourseOffering::factory()->create(['lecturer_id' => $lecturer->id]);
        $group = AssessmentGroup::factory()->create(['course_offering_id' => $offering->id]);

        $this->assertTrue($lecturer->can('update', $group));
    }

    public function test_lecturer_cannot_update_assessment_group_in_another_lecturers_offering(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $otherLecturer = User::factory()->lecturer()->create();
        $offering = CourseOffering::factory()->create(['lecturer_id' => $otherLecturer->id]);
        $group = AssessmentGroup::factory()->create(['course_offering_id' => $offering->id]);

        $this->assertFalse($lecturer->can('update', $group));
    }

    public function test_lecturer_cannot_delete_assessment_group(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $offering = CourseOffering::factory()->create(['lecturer_id' => $lecturer->id]);
        $group = AssessmentGroup::factory()->create(['course_offering_id' => $offering->id]);

        $this->assertFalse($lecturer->can('delete', $group));
    }
}
