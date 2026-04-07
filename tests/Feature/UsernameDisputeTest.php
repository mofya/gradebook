<?php

namespace Tests\Feature;

use App\Livewire\StudentVerification;
use App\Models\Course;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Year;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class UsernameDisputeTest extends TestCase
{
    use RefreshDatabase;

    private CourseOffering $offering;

    private Student $student;

    protected function setUp(): void
    {
        parent::setUp();

        $year = Year::factory()->create();
        $semester = Semester::factory()->create(['year_id' => $year->id]);
        $course = Course::factory()->create(['year_id' => $year->id]);

        $this->offering = CourseOffering::factory()->withVerificationToken()->create([
            'course_id' => $course->id,
            'semester_id' => $semester->id,
        ]);

        $this->student = Student::factory()->create(['student_id_number' => 'SNDIS001']);
        Enrollment::factory()->create([
            'student_id' => $this->student->id,
            'course_offering_id' => $this->offering->id,
        ]);
    }

    public function test_dispute_option_shown_when_username_taken(): void
    {
        Http::fake(['api.github.com/users/*' => Http::response(['login' => 'taken-user'], 200)]);
        Student::factory()->create(['github_username' => 'taken-user']);

        Livewire::test(StudentVerification::class, ['token' => $this->offering->verification_token])
            ->set('studentIdNumber', 'SNDIS001')
            ->call('verifyStudent')
            ->set('githubUsername', 'taken-user')
            ->call('updateDetails')
            ->assertSet('showDisputeOption', true)
            ->assertHasErrors('githubUsername');
    }

    public function test_file_dispute_creates_record(): void
    {
        Http::fake(['api.github.com/users/*' => Http::response(['login' => 'taken-user2'], 200)]);
        $holder = Student::factory()->create(['github_username' => 'taken-user2']);

        Livewire::test(StudentVerification::class, ['token' => $this->offering->verification_token])
            ->set('studentIdNumber', 'SNDIS001')
            ->call('verifyStudent')
            ->set('githubUsername', 'taken-user2')
            ->call('updateDetails')
            ->call('fileDispute')
            ->assertSet('disputeFiled', true)
            ->assertSet('showDisputeOption', false);

        $this->assertDatabaseHas('username_disputes', [
            'claimant_student_id' => $this->student->id,
            'current_holder_student_id' => $holder->id,
            'github_username' => 'taken-user2',
            'status' => 'pending',
        ]);
    }

    public function test_duplicate_dispute_prevented(): void
    {
        Http::fake(['api.github.com/users/*' => Http::response(['login' => 'taken-user3'], 200)]);
        $holder = Student::factory()->create(['github_username' => 'taken-user3']);

        // File first dispute
        $component = Livewire::test(StudentVerification::class, ['token' => $this->offering->verification_token])
            ->set('studentIdNumber', 'SNDIS001')
            ->call('verifyStudent')
            ->set('githubUsername', 'taken-user3')
            ->call('updateDetails')
            ->call('fileDispute')
            ->assertSet('disputeFiled', true);

        // Try filing again
        $component->call('fileDispute');

        $this->assertDatabaseCount('username_disputes', 1);
    }
}
