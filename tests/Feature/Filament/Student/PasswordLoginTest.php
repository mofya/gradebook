<?php

namespace Tests\Feature\Filament\Student;

use App\Filament\Student\Pages\Auth\OtpLogin;
use App\Models\Student;
use App\Models\User;
use App\Notifications\OtpLoginNotification;
use App\Services\OtpAuthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class PasswordLoginTest extends TestCase
{
    use RefreshDatabase;

    private function createRegisteredStudent(array $overrides = []): Student
    {
        return Student::factory()->create(array_merge([
            'email' => 'student@unza.zm',
            'personal_email' => 'student@gmail.com',
            'password' => 'password123',
            'registered_at' => now(),
        ], $overrides));
    }

    public function test_registered_student_sees_password_form_after_identifier(): void
    {
        Notification::fake();

        $student = $this->createRegisteredStudent();

        Livewire::test(OtpLogin::class)
            ->set('data.identifier', 'student@gmail.com')
            ->call('requestOtp')
            ->assertHasNoErrors()
            ->assertSet('step', 2)
            ->assertSet('studentHasPassword', true);
    }

    public function test_registered_student_can_login_with_password(): void
    {
        Notification::fake();

        $student = $this->createRegisteredStudent();
        $user = User::factory()->student()->create(['email' => 'student@gmail.com']);

        Livewire::test(OtpLogin::class)
            ->set('data.identifier', 'student@gmail.com')
            ->call('requestOtp')
            ->set('data.password', 'password123')
            ->call('loginWithPassword')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_registered_student_can_login_with_student_id(): void
    {
        Notification::fake();

        $student = $this->createRegisteredStudent([
            'student_id_number' => 'SN123456789',
        ]);
        $user = User::factory()->student()->create(['email' => 'student@gmail.com']);

        Livewire::test(OtpLogin::class)
            ->set('data.identifier', 'SN123456789')
            ->call('requestOtp')
            ->assertSet('step', 2)
            ->assertSet('studentHasPassword', true)
            ->set('data.password', 'password123')
            ->call('loginWithPassword')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_shows_error(): void
    {
        Notification::fake();

        $student = $this->createRegisteredStudent();
        User::factory()->student()->create(['email' => 'student@gmail.com']);

        Livewire::test(OtpLogin::class)
            ->set('data.identifier', 'student@gmail.com')
            ->call('requestOtp')
            ->set('data.password', 'wrong_password')
            ->call('loginWithPassword')
            ->assertHasErrors(['data.password']);
    }

    public function test_registered_student_can_switch_to_otp(): void
    {
        Notification::fake();

        $student = $this->createRegisteredStudent();

        Livewire::test(OtpLogin::class)
            ->set('data.identifier', 'student@gmail.com')
            ->call('requestOtp')
            ->assertSet('step', 2)
            ->call('switchToOtp')
            ->assertSet('step', 3);

        Notification::assertSentOnDemand(
            OtpLoginNotification::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'student@gmail.com';
            }
        );
    }

    public function test_unregistered_student_goes_straight_to_otp(): void
    {
        Notification::fake();

        $student = Student::factory()->create(['email' => 'student@unza.zm']);

        Livewire::test(OtpLogin::class)
            ->set('data.identifier', 'student@unza.zm')
            ->call('requestOtp')
            ->assertSet('step', 3)
            ->assertSet('studentHasPassword', false);
    }

    public function test_otp_sent_to_personal_email_for_registered_student(): void
    {
        Notification::fake();

        $student = $this->createRegisteredStudent();

        Livewire::test(OtpLogin::class)
            ->set('data.identifier', 'student@gmail.com')
            ->call('requestOtp')
            ->call('switchToOtp');

        Notification::assertSentOnDemand(
            OtpLoginNotification::class,
            function ($notification, $channels, $notifiable) {
                return $notifiable->routes['mail'] === 'student@gmail.com';
            }
        );
    }

    public function test_registered_student_can_login_via_otp(): void
    {
        Notification::fake();

        $student = $this->createRegisteredStudent();
        $user = User::factory()->student()->create(['email' => 'student@gmail.com']);

        $otpService = app(OtpAuthService::class);
        $code = $otpService->generateOtp('student@gmail.com');

        Livewire::test(OtpLogin::class)
            ->set('step', 3)
            ->set('studentId', $student->id)
            ->set('studentEmail', 'student@gmail.com')
            ->set('data.code', $code)
            ->call('verifyOtp')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }
}
