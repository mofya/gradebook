<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ManageLinksAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private CourseOffering $offering;

    private User $assignedLecturer;

    private User $otherLecturer;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->assignedLecturer = User::factory()->lecturer()->create();
        $this->otherLecturer = User::factory()->lecturer()->create();
        $this->admin = User::factory()->admin()->create();

        $year = Year::factory()->create();
        $semester = Semester::factory()->create(['year_id' => $year->id]);
        $course = Course::factory()->create(['year_id' => $year->id]);

        $this->offering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'lecturer_id' => $this->assignedLecturer->id,
        ]);
    }

    public function test_assigned_lecturer_can_access_manage_links(): void
    {
        $response = $this->actingAs($this->assignedLecturer)
            ->get("/admin/course-offerings/{$this->offering->id}/manage-links");

        $response->assertOk();
    }

    public function test_other_lecturer_cannot_access_manage_links(): void
    {
        $response = $this->actingAs($this->otherLecturer)
            ->get("/admin/course-offerings/{$this->offering->id}/manage-links");

        $response->assertForbidden();
    }

    public function test_admin_can_access_manage_links(): void
    {
        $response = $this->actingAs($this->admin)
            ->get("/admin/course-offerings/{$this->offering->id}/manage-links");

        $response->assertOk();
    }
}
