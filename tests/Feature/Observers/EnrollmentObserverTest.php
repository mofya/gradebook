<?php

namespace Tests\Feature\Observers;

use App\Enums\Role;
use App\Models\Enrollment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrollmentObserverTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_enrollment_auto_creates_user(): void
    {
        $student = Student::factory()->create(['email' => 'auto@example.com']);

        $this->assertDatabaseMissing('users', ['email' => 'auto@example.com']);

        Enrollment::factory()->create(['student_id' => $student->id]);

        $this->assertDatabaseHas('users', [
            'email' => 'auto@example.com',
            'role' => Role::Student->value,
        ]);
    }

    public function test_creating_enrollment_does_not_duplicate_user(): void
    {
        $student = Student::factory()->create(['email' => 'existing@example.com']);
        User::factory()->student()->create(['email' => 'existing@example.com']);

        Enrollment::factory()->create(['student_id' => $student->id]);

        $this->assertDatabaseCount('users', User::where('email', 'existing@example.com')->count());
        $this->assertEquals(1, User::where('email', 'existing@example.com')->count());
    }

    public function test_auto_created_user_has_student_role(): void
    {
        $student = Student::factory()->create();

        Enrollment::factory()->create(['student_id' => $student->id]);

        $user = User::where('email', $student->email)->first();

        $this->assertNotNull($user);
        $this->assertEquals(Role::Student, $user->role);
    }
}
