<?php

namespace Tests\Feature\Filament\Student;

use App\Filament\Student\Pages\Auth\StudentRegistration;
use App\Models\Student;
use App\Models\User;
use App\Notifications\OtpLoginNotification;
use App\Services\OtpAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class StudentRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Simulate successful OTP verification for a student in the session,
     * matching what lookupStudent() + verifyOtp() would do server-side.
     */
    private function simulateOtpVerification(Student $student): void
    {
        session()->put('registration-student-id', $student->id);
        session()->put("registration-verified:{$student->id}", true);
    }

    public function test_registration_page_renders(): void
    {
        $this->get('/student/register')
            ->assertSuccessful();
    }

    public function test_lookup_finds_student_and_sends_otp(): void
    {
        Notification::fake();

        $student = Student::factory()->create([
            'student_id_number' => 'SN123456789',
            'first_name' => 'John',
            'last_name' => 'Banda',
        ]);

        Livewire::test(StudentRegistration::class)
            ->set('data.student_id_number', 'SN123456789')
            ->call('lookupStudent')
            ->assertHasNoErrors()
            ->assertSet('step', 2)
            ->assertSet('studentName', 'John Banda')
            ->assertSet('studentId', $student->id);

        Notification::assertSentOnDemand(
            OtpLoginNotification::class,
            function ($notification, $channels, $notifiable) use ($student) {
                return $notifiable->routes['mail'] === $student->email;
            }
        );

        // Session should now hold the student ID
        $this->assertEquals($student->id, session('registration-student-id'));
    }

    public function test_lookup_fails_with_unknown_student_id(): void
    {
        Livewire::test(StudentRegistration::class)
            ->set('data.student_id_number', 'UNKNOWN123')
            ->call('lookupStudent')
            ->assertHasErrors(['data.student_id_number']);
    }

    public function test_lookup_fails_if_student_already_registered(): void
    {
        Student::factory()->create([
            'student_id_number' => 'SN123456789',
            'registered_at' => now(),
            'password' => 'secret123',
            'personal_email' => 'john@gmail.com',
        ]);

        Livewire::test(StudentRegistration::class)
            ->set('data.student_id_number', 'SN123456789')
            ->call('lookupStudent')
            ->assertHasErrors(['data.student_id_number']);
    }

    public function test_otp_verification_advances_to_account_step(): void
    {
        Notification::fake();

        $student = Student::factory()->create([
            'student_id_number' => 'SN123456789',
            'email' => 'sn123@unza.zm',
        ]);

        // lookupStudent stores the student ID in session
        session()->put('registration-student-id', $student->id);

        $otpService = app(OtpAuthService::class);
        $code = $otpService->generateOtp('sn123@unza.zm');

        Livewire::test(StudentRegistration::class)
            ->set('step', 2)
            ->set('studentId', $student->id)
            ->set('studentEmail', 'sn123@unza.zm')
            ->set('data.code', $code)
            ->call('verifyOtp')
            ->assertHasNoErrors()
            ->assertSet('step', 3);

        // Session proof should exist for this student
        $this->assertTrue(session()->has("registration-verified:{$student->id}"));
    }

    public function test_wrong_otp_shows_error(): void
    {
        Notification::fake();

        $student = Student::factory()->create([
            'student_id_number' => 'SN123456789',
            'email' => 'sn123@unza.zm',
        ]);

        session()->put('registration-student-id', $student->id);

        $otpService = app(OtpAuthService::class);
        $otpService->generateOtp('sn123@unza.zm');

        Livewire::test(StudentRegistration::class)
            ->set('step', 2)
            ->set('studentId', $student->id)
            ->set('studentEmail', 'sn123@unza.zm')
            ->set('data.code', '000000')
            ->call('verifyOtp')
            ->assertHasErrors(['data.code']);
    }

    public function test_register_blocked_without_otp_verification(): void
    {
        Http::fake();

        $student = Student::factory()->create([
            'student_id_number' => 'SN123456789',
        ]);

        // No session proof — forged request jumping straight to register
        Livewire::test(StudentRegistration::class)
            ->set('step', 3)
            ->set('studentId', $student->id)
            ->set('studentEmail', $student->email)
            ->set('data.personal_email', 'attacker@evil.com')
            ->set('data.password', 'password123')
            ->set('data.password_confirmation', 'password123')
            ->set('data.github_username', '')
            ->call('register')
            ->assertSet('step', 1)
            ->assertNotified();

        $student->refresh();
        $this->assertFalse($student->isRegistered());
    }

    public function test_register_blocked_with_mismatched_student_id(): void
    {
        Notification::fake();

        $studentA = Student::factory()->create([
            'student_id_number' => 'SN111111111',
            'email' => 'a@unza.zm',
        ]);

        $studentB = Student::factory()->create([
            'student_id_number' => 'SN222222222',
            'email' => 'b@unza.zm',
        ]);

        // Attacker verifies OTP for student A...
        session()->put('registration-student-id', $studentA->id);
        $otpService = app(OtpAuthService::class);
        $code = $otpService->generateOtp('a@unza.zm');

        Livewire::test(StudentRegistration::class)
            ->set('step', 2)
            ->set('studentId', $studentA->id)
            ->set('studentEmail', 'a@unza.zm')
            ->set('data.code', $code)
            ->call('verifyOtp')
            ->assertSet('step', 3);

        // ...then tries to register student B by changing the client property
        // The session proof is keyed to A, so register checks session student ID (A),
        // finds proof for A, and registers A — not B. B stays untouched.
        Livewire::test(StudentRegistration::class)
            ->set('step', 3)
            ->set('studentId', $studentB->id)
            ->set('studentEmail', 'b@unza.zm')
            ->set('data.personal_email', 'attacker@evil.com')
            ->set('data.password', 'password123')
            ->set('data.password_confirmation', 'password123')
            ->set('data.github_username', '')
            ->call('register');

        // Student B must NOT be registered
        $studentB->refresh();
        $this->assertFalse($studentB->isRegistered());
    }

    public function test_registration_creates_account_and_user(): void
    {
        Http::fake([
            'api.github.com/users/*' => Http::response(['login' => 'johndoe'], 200),
        ]);

        $student = Student::factory()->create([
            'student_id_number' => 'SN123456789',
            'first_name' => 'John',
            'last_name' => 'Banda',
            'github_username' => null,
        ]);

        $this->simulateOtpVerification($student);

        Livewire::test(StudentRegistration::class)
            ->set('step', 3)
            ->set('studentId', $student->id)
            ->set('studentEmail', $student->email)
            ->set('studentName', 'John Banda')
            ->set('data.personal_email', 'john@gmail.com')
            ->set('data.password', 'password123')
            ->set('data.password_confirmation', 'password123')
            ->set('data.github_username', 'johndoe')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect('/student/login');

        $student->refresh();
        $this->assertEquals('john@gmail.com', $student->personal_email);
        $this->assertEquals('johndoe', $student->github_username);
        $this->assertNotNull($student->registered_at);
        $this->assertTrue($student->isRegistered());

        $this->assertDatabaseHas('users', [
            'email' => 'john@gmail.com',
        ]);
    }

    public function test_registration_updates_existing_user_record(): void
    {
        Http::fake();

        $student = Student::factory()->create([
            'student_id_number' => 'SN123456789',
            'email' => 'sn123@unza.zm',
        ]);

        $user = User::factory()->student()->create([
            'email' => 'sn123@unza.zm',
        ]);

        $this->simulateOtpVerification($student);

        Livewire::test(StudentRegistration::class)
            ->set('step', 3)
            ->set('studentId', $student->id)
            ->set('studentEmail', $student->email)
            ->set('data.personal_email', 'john@gmail.com')
            ->set('data.password', 'password123')
            ->set('data.password_confirmation', 'password123')
            ->set('data.github_username', '')
            ->call('register')
            ->assertHasNoErrors();

        $user->refresh();
        $this->assertEquals('john@gmail.com', $user->email);
    }

    public function test_registration_fails_with_duplicate_personal_email(): void
    {
        Http::fake();

        Student::factory()->create([
            'personal_email' => 'taken@gmail.com',
            'registered_at' => now(),
            'password' => 'secret',
        ]);

        $student = Student::factory()->create([
            'student_id_number' => 'SN999999999',
        ]);

        $this->simulateOtpVerification($student);

        Livewire::test(StudentRegistration::class)
            ->set('step', 3)
            ->set('studentId', $student->id)
            ->set('studentEmail', $student->email)
            ->set('data.personal_email', 'taken@gmail.com')
            ->set('data.password', 'password123')
            ->set('data.password_confirmation', 'password123')
            ->set('data.github_username', '')
            ->call('register')
            ->assertHasErrors(['data.personal_email']);
    }

    public function test_registration_fails_with_institutional_email_as_personal(): void
    {
        Http::fake();

        Student::factory()->create([
            'email' => 'other@unza.zm',
        ]);

        $student = Student::factory()->create([
            'student_id_number' => 'SN123456789',
        ]);

        $this->simulateOtpVerification($student);

        Livewire::test(StudentRegistration::class)
            ->set('step', 3)
            ->set('studentId', $student->id)
            ->set('studentEmail', $student->email)
            ->set('data.personal_email', 'other@unza.zm')
            ->set('data.password', 'password123')
            ->set('data.password_confirmation', 'password123')
            ->set('data.github_username', '')
            ->call('register')
            ->assertHasErrors(['data.personal_email']);
    }

    public function test_registration_fails_with_invalid_github_username(): void
    {
        Http::fake([
            'api.github.com/users/nonexistent_user_xyz' => Http::response(null, 404),
        ]);

        $student = Student::factory()->create([
            'student_id_number' => 'SN123456789',
        ]);

        $this->simulateOtpVerification($student);

        Livewire::test(StudentRegistration::class)
            ->set('step', 3)
            ->set('studentId', $student->id)
            ->set('studentEmail', $student->email)
            ->set('data.personal_email', 'john@gmail.com')
            ->set('data.password', 'password123')
            ->set('data.password_confirmation', 'password123')
            ->set('data.github_username', 'nonexistent_user_xyz')
            ->call('register')
            ->assertHasErrors(['data.github_username']);
    }

    public function test_registration_preserves_existing_github_on_empty_input(): void
    {
        Http::fake();

        $student = Student::factory()->create([
            'student_id_number' => 'SN123456789',
            'github_username' => 'original_user',
        ]);

        $this->simulateOtpVerification($student);

        Livewire::test(StudentRegistration::class)
            ->set('step', 3)
            ->set('studentId', $student->id)
            ->set('studentEmail', $student->email)
            ->set('existingGithub', 'original_user')
            ->set('data.personal_email', 'john@gmail.com')
            ->set('data.password', 'password123')
            ->set('data.password_confirmation', 'password123')
            ->set('data.github_username', '')
            ->call('register')
            ->assertHasNoErrors();

        $student->refresh();
        $this->assertEquals('original_user', $student->github_username);
    }

    public function test_registration_requires_password_minimum_length(): void
    {
        $student = Student::factory()->create([
            'student_id_number' => 'SN123456789',
        ]);

        $this->simulateOtpVerification($student);

        Livewire::test(StudentRegistration::class)
            ->set('step', 3)
            ->set('studentId', $student->id)
            ->set('studentEmail', $student->email)
            ->set('data.personal_email', 'john@gmail.com')
            ->set('data.password', 'short')
            ->set('data.password_confirmation', 'short')
            ->set('data.github_username', '')
            ->call('register')
            ->assertHasErrors(['data.password']);
    }

    public function test_go_back_resets_to_step_one(): void
    {
        Notification::fake();

        $student = Student::factory()->create([
            'student_id_number' => 'SN123456789',
        ]);

        Livewire::test(StudentRegistration::class)
            ->set('data.student_id_number', 'SN123456789')
            ->call('lookupStudent')
            ->assertSet('step', 2)
            ->call('goBack')
            ->assertSet('step', 1)
            ->assertSet('studentId', null)
            ->assertSet('studentName', null)
            ->assertSet('studentEmail', null);

        // Session should be cleared
        $this->assertNull(session('registration-student-id'));
    }

    public function test_full_registration_flow(): void
    {
        Notification::fake();
        Http::fake([
            'api.github.com/users/*' => Http::response(['login' => 'testuser'], 200),
        ]);

        $student = Student::factory()->create([
            'student_id_number' => 'SN123456789',
            'email' => 'sn123@unza.zm',
            'first_name' => 'John',
            'last_name' => 'Banda',
        ]);

        $otpService = app(OtpAuthService::class);

        // Step 1: Lookup
        $component = Livewire::test(StudentRegistration::class)
            ->set('data.student_id_number', 'SN123456789')
            ->call('lookupStudent')
            ->assertSet('step', 2);

        // Generate a fresh OTP we know the code for
        $code = $otpService->generateOtp('sn123@unza.zm');

        // Step 2: Verify OTP
        $component
            ->set('data.code', $code)
            ->call('verifyOtp')
            ->assertHasNoErrors()
            ->assertSet('step', 3);

        // Step 3: Complete registration
        $component
            ->set('data.personal_email', 'john@gmail.com')
            ->set('data.password', 'password123')
            ->set('data.password_confirmation', 'password123')
            ->set('data.github_username', 'testuser')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect('/student/login');

        $student->refresh();
        $this->assertTrue($student->isRegistered());
        $this->assertEquals('john@gmail.com', $student->personal_email);
    }

    public function test_validation_error_allows_retry_without_restarting(): void
    {
        Http::fake([
            'api.github.com/users/baduser' => Http::response(null, 404),
            'api.github.com/users/gooduser' => Http::response(['login' => 'gooduser'], 200),
        ]);

        $student = Student::factory()->create([
            'student_id_number' => 'SN123456789',
        ]);

        $this->simulateOtpVerification($student);

        $component = Livewire::test(StudentRegistration::class)
            ->set('step', 3)
            ->set('studentId', $student->id)
            ->set('studentEmail', $student->email);

        // First attempt — fails on invalid GitHub username
        $component
            ->set('data.personal_email', 'john@gmail.com')
            ->set('data.password', 'password123')
            ->set('data.password_confirmation', 'password123')
            ->set('data.github_username', 'baduser')
            ->call('register')
            ->assertHasErrors(['data.github_username'])
            ->assertSet('step', 3);

        // Second attempt — should succeed, proof still valid
        $component
            ->set('data.github_username', 'gooduser')
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect('/student/login');

        $student->refresh();
        $this->assertTrue($student->isRegistered());
    }

    public function test_stale_proof_invalidated_on_new_lookup(): void
    {
        Notification::fake();

        $student = Student::factory()->create([
            'student_id_number' => 'SN123456789',
            'email' => 'sn123@unza.zm',
        ]);

        // Simulate a previous abandoned attempt that left a stale proof
        session()->put('registration-student-id', $student->id);
        session()->put("registration-verified:{$student->id}", true);

        // New lookup for the same student should clear the stale proof
        Livewire::test(StudentRegistration::class)
            ->set('data.student_id_number', 'SN123456789')
            ->call('lookupStudent')
            ->assertSet('step', 2);

        // Stale proof should be gone — a fresh OTP is required
        $this->assertFalse(session()->has("registration-verified:{$student->id}"));

        // Attempting to register without re-verifying should be blocked
        Livewire::test(StudentRegistration::class)
            ->set('step', 3)
            ->set('studentId', $student->id)
            ->set('studentEmail', $student->email)
            ->set('data.personal_email', 'john@gmail.com')
            ->set('data.password', 'password123')
            ->set('data.password_confirmation', 'password123')
            ->set('data.github_username', '')
            ->call('register')
            ->assertSet('step', 1)
            ->assertNotified();

        $student->refresh();
        $this->assertFalse($student->isRegistered());
    }
}
