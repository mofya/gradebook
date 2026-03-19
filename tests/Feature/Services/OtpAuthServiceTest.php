<?php

namespace Tests\Feature\Services;

use App\Enums\Role;
use App\Models\OtpCode;
use App\Models\Student;
use App\Models\User;
use App\Notifications\OtpLoginNotification;
use App\Services\OtpAuthService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class OtpAuthServiceTest extends TestCase
{
    use RefreshDatabase;

    private OtpAuthService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(OtpAuthService::class);
    }

    public function test_resolve_student_by_email(): void
    {
        $student = Student::factory()->create(['email' => 'john@example.com']);

        $found = $this->service->resolveStudent('john@example.com');

        $this->assertNotNull($found);
        $this->assertEquals($student->id, $found->id);
    }

    public function test_resolve_student_by_student_id_number(): void
    {
        $student = Student::factory()->create(['student_id_number' => 'SN123456789']);

        $found = $this->service->resolveStudent('SN123456789');

        $this->assertNotNull($found);
        $this->assertEquals($student->id, $found->id);
    }

    public function test_resolve_student_returns_null_for_unknown(): void
    {
        $found = $this->service->resolveStudent('nonexistent@example.com');

        $this->assertNull($found);
    }

    public function test_generate_otp_creates_six_digit_code(): void
    {
        $code = $this->service->generateOtp('test@example.com');

        $this->assertMatchesRegularExpression('/^\d{6}$/', $code);
        $this->assertDatabaseCount('otp_codes', 1);
    }

    public function test_generate_otp_invalidates_previous_codes(): void
    {
        $this->service->generateOtp('test@example.com');
        $this->service->generateOtp('test@example.com');

        $activeCount = OtpCode::query()
            ->where('email', 'test@example.com')
            ->whereNull('verified_at')
            ->whereNull('revoked_at')
            ->count();

        $this->assertEquals(1, $activeCount);
    }

    public function test_verify_otp_succeeds_with_correct_code(): void
    {
        $code = $this->service->generateOtp('test@example.com');

        $result = $this->service->verifyOtp('test@example.com', $code);

        $this->assertTrue($result['success']);
    }

    public function test_verify_otp_fails_with_wrong_code(): void
    {
        $this->service->generateOtp('test@example.com');

        $result = $this->service->verifyOtp('test@example.com', '000000');

        $this->assertFalse($result['success']);
        $this->assertEquals('Invalid verification code.', $result['message']);
    }

    public function test_verify_otp_fails_when_expired(): void
    {
        $code = $this->service->generateOtp('test@example.com');

        OtpCode::query()->update(['expires_at' => now()->subMinutes(1)]);

        $result = $this->service->verifyOtp('test@example.com', $code);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('expired', $result['message']);
    }

    public function test_verify_otp_fails_after_max_attempts(): void
    {
        $this->service->generateOtp('test@example.com');

        OtpCode::query()->update(['attempts' => 5]);

        $result = $this->service->verifyOtp('test@example.com', '123456');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Too many', $result['message']);
    }

    public function test_ensure_user_exists_creates_new_student_user(): void
    {
        $student = Student::factory()->create([
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => 'jane@example.com',
        ]);

        $user = $this->service->ensureUserExists($student);

        $this->assertInstanceOf(User::class, $user);
        $this->assertEquals('jane@example.com', $user->email);
        $this->assertEquals('Jane Doe', $user->name);
        $this->assertEquals(Role::Student, $user->role);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_ensure_user_exists_returns_existing_user(): void
    {
        $existingUser = User::factory()->student()->create(['email' => 'jane@example.com']);
        $student = Student::factory()->create(['email' => 'jane@example.com']);

        $user = $this->service->ensureUserExists($student);

        $this->assertEquals($existingUser->id, $user->id);
        $this->assertDatabaseCount('users', 1);
    }

    public function test_throttle_check_allows_first_three_requests(): void
    {
        RateLimiter::clear('otp-request:test@example.com');

        for ($i = 0; $i < 3; $i++) {
            $result = $this->service->throttleCheck('test@example.com');
            $this->assertTrue($result['allowed'], "Request {$i} should be allowed");
        }

        $result = $this->service->throttleCheck('test@example.com');
        $this->assertFalse($result['allowed']);
        $this->assertGreaterThan(0, $result['seconds']);
    }

    public function test_throttle_check_is_per_identifier(): void
    {
        RateLimiter::clear('otp-request:user-a@example.com');
        RateLimiter::clear('otp-request:user-b@example.com');

        for ($i = 0; $i < 3; $i++) {
            $this->service->throttleCheck('user-a@example.com');
        }

        $result = $this->service->throttleCheck('user-b@example.com');
        $this->assertTrue($result['allowed']);
    }

    public function test_can_resend_enforces_sixty_second_cooldown(): void
    {
        RateLimiter::clear('otp-resend:test@example.com');

        $first = $this->service->canResend('test@example.com');
        $this->assertTrue($first);

        $second = $this->service->canResend('test@example.com');
        $this->assertFalse($second);
    }

    public function test_otp_notification_is_queued(): void
    {
        $notification = new OtpLoginNotification('123456');

        $this->assertInstanceOf(ShouldQueue::class, $notification);
    }
}
