<?php

namespace Tests\Feature\Api;

use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Semester;
use App\Models\Student;
use App\Models\User;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StudentApiTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private CourseOffering $offering;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->admin()->create();

        $year = Year::factory()->create();
        $semester = Semester::factory()->create(['year_id' => $year->id]);
        $course = Course::factory()->create(['year_id' => $year->id]);

        $this->offering = CourseOffering::factory()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
            'lecturer_id' => $this->admin->id,
        ]);
    }

    public function test_create_student(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/students', [
                'student_id_number' => 'SNCREATE001',
                'first_name' => 'Jane',
                'last_name' => 'Doe',
                'email' => 'jane.doe@test.com',
                'gender' => 'Female',
                'program' => 'Computer Science',
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.student_id_number', 'SNCREATE001')
            ->assertJsonPath('data.first_name', 'Jane')
            ->assertJsonPath('data.created', true)
            ->assertJsonPath('data.enrolled', false);

        $this->assertDatabaseHas('students', ['student_id_number' => 'SNCREATE001']);
    }

    public function test_create_student_and_enroll(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/students', [
                'student_id_number' => 'SNCREATE002',
                'first_name' => 'John',
                'last_name' => 'Smith',
                'email' => 'john.smith@test.com',
                'offering_id' => $this->offering->id,
            ]);

        $response->assertCreated()
            ->assertJsonPath('data.created', true)
            ->assertJsonPath('data.enrolled', true);

        $this->assertDatabaseHas('enrollments', [
            'course_offering_id' => $this->offering->id,
            'source' => 'api',
        ]);
    }

    public function test_create_existing_student_updates_fields(): void
    {
        Student::factory()->create([
            'student_id_number' => 'SNCREATE003',
            'first_name' => 'Old',
            'email' => 'old@test.com',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/students', [
                'student_id_number' => 'SNCREATE003',
                'first_name' => 'Updated',
                'last_name' => 'Name',
                'email' => 'updated@test.com',
                'github_username' => 'newgithub',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.created', false)
            ->assertJsonPath('data.github_username', 'newgithub');
    }

    public function test_create_student_rejects_duplicate_email(): void
    {
        Student::factory()->create(['email' => 'taken@test.com']);

        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/students', [
                'student_id_number' => 'SNCREATE004',
                'first_name' => 'New',
                'last_name' => 'Student',
                'email' => 'taken@test.com',
            ])
            ->assertStatus(422);
    }

    public function test_create_student_validates_required_fields(): void
    {
        $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/students', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['student_id_number', 'first_name', 'last_name', 'email']);
    }

    public function test_bulk_create_students(): void
    {
        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/students/bulk', [
                'students' => [
                    [
                        'student_id_number' => 'SNBULK001',
                        'first_name' => 'Alice',
                        'last_name' => 'One',
                        'email' => 'alice@test.com',
                    ],
                    [
                        'student_id_number' => 'SNBULK002',
                        'first_name' => 'Bob',
                        'last_name' => 'Two',
                        'email' => 'bob@test.com',
                    ],
                ],
                'offering_id' => $this->offering->id,
            ]);

        $response->assertOk()
            ->assertJsonPath('data.created', 2)
            ->assertJsonPath('data.enrolled', 2)
            ->assertJsonPath('data.errors', []);

        $this->assertDatabaseCount('enrollments', 2);
    }

    public function test_bulk_create_updates_existing_students(): void
    {
        Student::factory()->create([
            'student_id_number' => 'SNBULK003',
            'first_name' => 'Old',
            'email' => 'old@test.com',
        ]);

        $response = $this->actingAs($this->admin, 'sanctum')
            ->postJson('/api/v1/students/bulk', [
                'students' => [
                    [
                        'student_id_number' => 'SNBULK003',
                        'first_name' => 'Updated',
                        'last_name' => 'Name',
                        'email' => 'updated@test.com',
                    ],
                ],
            ]);

        $response->assertOk()
            ->assertJsonPath('data.created', 0)
            ->assertJsonPath('data.updated', 1);
    }

    public function test_student_user_cannot_create_students(): void
    {
        $student = User::factory()->student()->create();

        $this->actingAs($student, 'sanctum')
            ->postJson('/api/v1/students', [
                'student_id_number' => 'SN999',
                'first_name' => 'No',
                'last_name' => 'Access',
                'email' => 'no@test.com',
            ])
            ->assertForbidden();
    }
}
