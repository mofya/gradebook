<?php

namespace Tests\Feature\Policies;

use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_any_enrollment(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->create();

        $this->assertTrue($admin->can('view', $enrollment));
    }

    public function test_lecturer_can_view_enrollment_in_own_offering(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $offering = CourseOffering::factory()->create(['lecturer_id' => $lecturer->id]);
        $enrollment = Enrollment::factory()->create(['course_offering_id' => $offering->id]);

        $this->assertTrue($lecturer->can('view', $enrollment));
    }

    public function test_lecturer_cannot_view_enrollment_in_another_lecturers_offering(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $otherLecturer = User::factory()->lecturer()->create();
        $offering = CourseOffering::factory()->create(['lecturer_id' => $otherLecturer->id]);
        $enrollment = Enrollment::factory()->create(['course_offering_id' => $offering->id]);

        $this->assertFalse($lecturer->can('view', $enrollment));
    }

    public function test_admin_can_update_any_enrollment(): void
    {
        $admin = User::factory()->admin()->create();
        $enrollment = Enrollment::factory()->create();

        $this->assertTrue($admin->can('update', $enrollment));
    }

    public function test_lecturer_can_update_enrollment_in_own_offering(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $offering = CourseOffering::factory()->create(['lecturer_id' => $lecturer->id]);
        $enrollment = Enrollment::factory()->create(['course_offering_id' => $offering->id]);

        $this->assertTrue($lecturer->can('update', $enrollment));
    }

    public function test_lecturer_cannot_update_enrollment_in_another_lecturers_offering(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $otherLecturer = User::factory()->lecturer()->create();
        $offering = CourseOffering::factory()->create(['lecturer_id' => $otherLecturer->id]);
        $enrollment = Enrollment::factory()->create(['course_offering_id' => $offering->id]);

        $this->assertFalse($lecturer->can('update', $enrollment));
    }

    public function test_lecturer_cannot_delete_enrollment(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $offering = CourseOffering::factory()->create(['lecturer_id' => $lecturer->id]);
        $enrollment = Enrollment::factory()->create(['course_offering_id' => $offering->id]);

        $this->assertFalse($lecturer->can('delete', $enrollment));
    }

    public function test_student_cannot_view_enrollment(): void
    {
        $student = User::factory()->student()->create();
        $enrollment = Enrollment::factory()->create();

        $this->assertFalse($student->can('view', $enrollment));
    }
}
