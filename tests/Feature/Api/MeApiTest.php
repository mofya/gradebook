<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MeApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_me_returns_authenticated_user(): void
    {
        $user = User::factory()->admin()->create(['name' => 'Test Admin']);

        $response = $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me');

        $response->assertOk()
            ->assertJsonPath('data.name', 'Test Admin')
            ->assertJsonPath('data.email', $user->email)
            ->assertJsonPath('data.role', 'admin')
            ->assertJsonStructure(['data' => ['id', 'name', 'email', 'role']]);
    }

    public function test_me_works_for_lecturer(): void
    {
        $user = User::factory()->lecturer()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.role', 'lecturer');
    }

    public function test_me_works_for_student(): void
    {
        $user = User::factory()->student()->create();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.role', 'student');
    }

    public function test_me_returns_401_without_token(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized();
    }
}
