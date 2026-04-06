<?php

namespace Tests\Feature\Policies;

use App\Models\CourseOffering;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseOfferingPolicyTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_view_any_offering(): void
    {
        $admin = User::factory()->admin()->create();
        $offering = CourseOffering::factory()->create();

        $this->assertTrue($admin->can('view', $offering));
    }

    public function test_lecturer_can_view_own_offering(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $offering = CourseOffering::factory()->create(['lecturer_id' => $lecturer->id]);

        $this->assertTrue($lecturer->can('view', $offering));
    }

    public function test_lecturer_cannot_view_another_lecturers_offering(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $otherLecturer = User::factory()->lecturer()->create();
        $offering = CourseOffering::factory()->create(['lecturer_id' => $otherLecturer->id]);

        $this->assertFalse($lecturer->can('view', $offering));
    }

    public function test_admin_can_update_any_offering(): void
    {
        $admin = User::factory()->admin()->create();
        $offering = CourseOffering::factory()->create();

        $this->assertTrue($admin->can('update', $offering));
    }

    public function test_lecturer_can_update_own_offering(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $offering = CourseOffering::factory()->create(['lecturer_id' => $lecturer->id]);

        $this->assertTrue($lecturer->can('update', $offering));
    }

    public function test_lecturer_cannot_update_another_lecturers_offering(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $otherLecturer = User::factory()->lecturer()->create();
        $offering = CourseOffering::factory()->create(['lecturer_id' => $otherLecturer->id]);

        $this->assertFalse($lecturer->can('update', $offering));
    }

    public function test_admin_can_delete_offering(): void
    {
        $admin = User::factory()->admin()->create();
        $offering = CourseOffering::factory()->create();

        $this->assertTrue($admin->can('delete', $offering));
    }

    public function test_lecturer_cannot_delete_offering(): void
    {
        $lecturer = User::factory()->lecturer()->create();
        $offering = CourseOffering::factory()->create(['lecturer_id' => $lecturer->id]);

        $this->assertFalse($lecturer->can('delete', $offering));
    }

    public function test_student_cannot_view_any_offering(): void
    {
        $student = User::factory()->student()->create();
        $offering = CourseOffering::factory()->create();

        $this->assertFalse($student->can('view', $offering));
    }

    public function test_any_lecturer_can_view_any_list(): void
    {
        $lecturer = User::factory()->lecturer()->create();

        $this->assertTrue($lecturer->can('viewAny', CourseOffering::class));
    }

    public function test_any_lecturer_can_create_offering(): void
    {
        $lecturer = User::factory()->lecturer()->create();

        $this->assertTrue($lecturer->can('create', CourseOffering::class));
    }
}
