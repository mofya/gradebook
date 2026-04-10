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

class StudentVerificationTest extends TestCase
{
    use RefreshDatabase;

    private CourseOffering $offering;

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
    }

    public function test_verification_page_loads_with_valid_token(): void
    {
        $response = $this->get(route('student.verify', ['token' => $this->offering->verification_token]));

        $response->assertOk();
        $response->assertSee($this->offering->course->code);
    }

    public function test_expired_token_shows_expired_message(): void
    {
        $offering = CourseOffering::factory()->withVerificationToken(-1)->create();

        Livewire::test(StudentVerification::class, ['token' => $offering->verification_token])
            ->assertSet('step', 'expired')
            ->assertSee('Link Expired');
    }

    public function test_invalid_token_shows_expired_message(): void
    {
        Livewire::test(StudentVerification::class, ['token' => 'nonexistent-token-12345'])
            ->assertSet('step', 'expired');
    }

    public function test_student_lookup_finds_enrolled_student(): void
    {
        $student = Student::factory()->create(['student_id_number' => 'SN111000001']);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
        ]);

        Livewire::test(StudentVerification::class, ['token' => $this->offering->verification_token])
            ->set('studentIdNumber', 'SN111000001')
            ->call('verifyStudent')
            ->assertSet('step', 'review')
            ->assertSet('studentName', $student->first_name.' '.$student->last_name)
            ->assertSet('studentEmail', $student->email);
    }

    public function test_student_lookup_fails_for_unenrolled_student(): void
    {
        Student::factory()->create(['student_id_number' => 'SN111000002']);

        Livewire::test(StudentVerification::class, ['token' => $this->offering->verification_token])
            ->set('studentIdNumber', 'SN111000002')
            ->call('verifyStudent')
            ->assertSet('step', 'not_found');
    }

    public function test_student_lookup_fails_for_nonexistent_student(): void
    {
        Livewire::test(StudentVerification::class, ['token' => $this->offering->verification_token])
            ->set('studentIdNumber', 'DOESNOTEXIST')
            ->call('verifyStudent')
            ->assertSet('step', 'not_found');
    }

    public function test_update_github_username(): void
    {
        Http::fake([
            'api.github.com/users/*' => Http::response(['login' => 'newuser'], 200),
        ]);

        $student = Student::factory()->create(['student_id_number' => 'SN111000003']);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
        ]);

        Livewire::test(StudentVerification::class, ['token' => $this->offering->verification_token])
            ->set('studentIdNumber', 'SN111000003')
            ->call('verifyStudent')
            ->assertSet('step', 'review')
            ->call('proceedToEdit')
            ->assertSet('step', 'found')
            ->set('githubUsername', 'newuser')
            ->set('personalEmail', 'newuser@gmail.com')
            ->call('updateDetails')
            ->assertSet('step', 'updated');

        $student->refresh();
        $this->assertEquals('newuser', $student->github_username);
        $this->assertEquals('newuser@gmail.com', $student->personal_email);
    }

    public function test_update_github_validates_against_api(): void
    {
        Http::fake([
            'api.github.com/users/*' => Http::response([], 404),
        ]);

        $student = Student::factory()->create(['student_id_number' => 'SN111000004']);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
        ]);

        Livewire::test(StudentVerification::class, ['token' => $this->offering->verification_token])
            ->set('studentIdNumber', 'SN111000004')
            ->call('verifyStudent')
            ->call('proceedToEdit')
            ->set('githubUsername', 'doesnotexist999')
            ->call('updateDetails')
            ->assertHasErrors('githubUsername');
    }

    public function test_update_github_checks_uniqueness(): void
    {
        Http::fake([
            'api.github.com/users/*' => Http::response(['login' => 'taken'], 200),
        ]);

        Student::factory()->create(['github_username' => 'taken']);
        $student = Student::factory()->create(['student_id_number' => 'SN111000005']);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
        ]);

        Livewire::test(StudentVerification::class, ['token' => $this->offering->verification_token])
            ->set('studentIdNumber', 'SN111000005')
            ->call('verifyStudent')
            ->call('proceedToEdit')
            ->set('githubUsername', 'taken')
            ->call('updateDetails')
            ->assertHasErrors('githubUsername');
    }

    public function test_audit_log_created_on_update(): void
    {
        Http::fake([
            'api.github.com/users/*' => Http::response(['login' => 'audituser'], 200),
        ]);

        $student = Student::factory()->create(['student_id_number' => 'SN111000006']);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
        ]);

        Livewire::test(StudentVerification::class, ['token' => $this->offering->verification_token])
            ->set('studentIdNumber', 'SN111000006')
            ->call('verifyStudent')
            ->call('proceedToEdit')
            ->set('githubUsername', 'audituser')
            ->call('updateDetails');

        $this->assertDatabaseHas('grade_audit_logs', [
            'auditable_type' => Student::class,
            'auditable_id' => $student->id,
            'action' => 'verification_form_update',
            'user_id' => null,
        ]);
    }

    public function test_reset_lookup_returns_to_lookup_step(): void
    {
        $student = Student::factory()->create(['student_id_number' => 'SN111000007']);
        Enrollment::factory()->create([
            'student_id' => $student->id,
            'course_offering_id' => $this->offering->id,
        ]);

        Livewire::test(StudentVerification::class, ['token' => $this->offering->verification_token])
            ->set('studentIdNumber', 'SN111000007')
            ->call('verifyStudent')
            ->assertSet('step', 'review')
            ->call('proceedToEdit')
            ->assertSet('step', 'found')
            ->call('resetLookup')
            ->assertSet('step', 'lookup')
            ->assertSet('studentIdNumber', '');
    }
}
