<?php

namespace Tests\Feature\Filament\Student;

use App\Filament\Student\Pages\Auth\OtpLogin;
use App\Models\Student;
use App\Models\User;
use App\Notifications\OtpLoginNotification;
use App\Services\OtpAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\TestCase;

class OtpLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_otp_login_page_renders(): void
    {
        $this->get('/student/login')
            ->assertSuccessful();
    }

    public function test_request_otp_with_valid_email_shows_code_input(): void
    {
        Notification::fake();

        $student = Student::factory()->create(['email' => 'student@example.com']);

        Livewire::test(OtpLogin::class)
            ->set('data.identifier', 'student@example.com')
            ->call('requestOtp')
            ->assertHasNoErrors()
            ->assertSet('step', 3)
            ->assertSet('studentEmail', 'student@example.com');

        // Session should now hold the student ID
        $this->assertEquals($student->id, session('login-student-id'));
    }

    public function test_request_otp_with_unknown_identifier_shows_error(): void
    {
        Livewire::test(OtpLogin::class)
            ->set('data.identifier', 'unknown@example.com')
            ->call('requestOtp')
            ->assertHasErrors(['data.identifier']);
    }

    public function test_request_otp_sends_notification(): void
    {
        Notification::fake();

        $student = Student::factory()->create(['email' => 'student@example.com']);

        Livewire::test(OtpLogin::class)
            ->set('data.identifier', 'student@example.com')
            ->call('requestOtp');

        Notification::assertSentOnDemand(
            OtpLoginNotification::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'student@example.com';
            }
        );
    }

    public function test_verify_correct_otp_logs_in_student(): void
    {
        Notification::fake();

        $student = Student::factory()->create(['email' => 'student@example.com']);
        $user = User::factory()->student()->create(['email' => 'student@example.com']);

        session()->put('login-student-id', $student->id);

        $otpService = app(OtpAuthService::class);
        $code = $otpService->generateOtp('student@example.com');

        Livewire::test(OtpLogin::class)
            ->set('step', 3)
            ->set('studentId', $student->id)
            ->set('studentEmail', 'student@example.com')
            ->set('data.code', $code)
            ->call('verifyOtp')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_verify_wrong_otp_shows_error(): void
    {
        Notification::fake();

        $student = Student::factory()->create(['email' => 'student@example.com']);
        User::factory()->student()->create(['email' => 'student@example.com']);

        session()->put('login-student-id', $student->id);

        $otpService = app(OtpAuthService::class);
        $otpService->generateOtp('student@example.com');

        Livewire::test(OtpLogin::class)
            ->set('step', 3)
            ->set('studentId', $student->id)
            ->set('studentEmail', 'student@example.com')
            ->set('data.code', '000000')
            ->call('verifyOtp')
            ->assertHasErrors(['data.code']);
    }

    public function test_verify_otp_blocked_with_mismatched_student_id(): void
    {
        Notification::fake();

        $studentA = Student::factory()->create(['email' => 'a@example.com']);
        $studentB = Student::factory()->create(['email' => 'b@example.com']);
        User::factory()->student()->create(['email' => 'b@example.com']);

        // Session bound to student A
        session()->put('login-student-id', $studentA->id);

        $otpService = app(OtpAuthService::class);
        $code = $otpService->generateOtp('a@example.com');

        // Attacker sets studentId to B but uses A's OTP
        // verifyOtp() should verify against A (session), not B (client)
        // The OTP is valid for A's email, and the user logged in would be A, not B
        Livewire::test(OtpLogin::class)
            ->set('step', 3)
            ->set('studentId', $studentB->id)
            ->set('studentEmail', 'b@example.com')
            ->set('data.code', $code)
            ->call('verifyOtp')
            ->assertHasNoErrors()
            ->assertRedirect();

        // Should be authenticated as A's user, not B's
        $this->assertAuthenticated();
        $this->assertEquals('a@example.com', auth()->user()->email);
    }

    public function test_request_otp_blocked_after_three_per_identifier(): void
    {
        Notification::fake();

        $student = Student::factory()->create(['email' => 'throttle@example.com']);

        RateLimiter::clear('otp-request:throttle@example.com');
        RateLimiter::clear('otp-resend:throttle@example.com');

        $component = Livewire::test(OtpLogin::class);

        for ($i = 0; $i < 3; $i++) {
            RateLimiter::clear('otp-resend:throttle@example.com');
            $component
                ->set('step', 1)
                ->set('data.identifier', 'throttle@example.com')
                ->call('requestOtp');
        }

        // 4th request should be blocked by per-identifier throttle
        $component
            ->set('step', 1)
            ->set('data.identifier', 'throttle@example.com')
            ->call('requestOtp')
            ->assertSet('step', 1)
            ->assertNotified();
    }

    public function test_can_resolve_student_by_personal_email(): void
    {
        Notification::fake();

        $student = Student::factory()->create([
            'email' => 'student@unza.zm',
            'personal_email' => 'student@gmail.com',
        ]);

        Livewire::test(OtpLogin::class)
            ->set('data.identifier', 'student@gmail.com')
            ->call('requestOtp')
            ->assertHasNoErrors()
            ->assertSet('studentEmail', 'student@gmail.com');
    }

    public function test_case_insensitive_login(): void
    {
        Notification::fake();

        $student = Student::factory()->create([
            'email' => 'student@unza.zm',
            'personal_email' => 'student@gmail.com',
        ]);

        Livewire::test(OtpLogin::class)
            ->set('data.identifier', 'Student@Gmail.COM')
            ->call('requestOtp')
            ->assertHasNoErrors()
            ->assertSet('studentId', $student->id);
    }
}
