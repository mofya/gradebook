<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CourseApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_list_courses(): void
    {
        $user = User::factory()->admin()->create();
        $year = Year::factory()->create();
        Course::factory()->count(3)->create(['year_id' => $year->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/courses');

        $response->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_unauthenticated_user_cannot_list_courses(): void
    {
        $response = $this->getJson('/api/courses');

        $response->assertUnauthorized();
    }

    public function test_authenticated_user_can_view_a_course(): void
    {
        $user = User::factory()->admin()->create();
        $year = Year::factory()->create();
        $course = Course::factory()->create(['year_id' => $year->id]);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/courses/'.$course->id);

        $response->assertOk()
            ->assertJsonPath('data.id', $course->id)
            ->assertJsonPath('data.code', $course->code);
    }
}
