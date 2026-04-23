<?php

namespace Tests\Feature;

use App\Models\Course;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RenameCourseCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_updates_course_code(): void
    {
        $course = Course::factory()->create(['code' => 'OLD001']);

        $this->artisan('app:rename-course-code OLD001 NEW001')->assertSuccessful();

        $this->assertEquals('NEW001', $course->fresh()->code);
    }

    public function test_is_idempotent_when_already_renamed(): void
    {
        Course::factory()->create(['code' => 'NEW001']);

        $this->artisan('app:rename-course-code OLD001 NEW001')->assertSuccessful();
    }

    public function test_fails_when_old_code_missing_and_new_also_missing(): void
    {
        $this->artisan('app:rename-course-code OLD001 NEW001')->assertFailed();
    }
}
