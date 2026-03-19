<?php

namespace App\Services;

use App\Enums\Role;
use App\Models\OtpCode;
use App\Models\Student;
use App\Models\User;
use App\Notifications\OtpLoginNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class OtpAuthService
{
    /**
     * Check if an identifier is allowed to request an OTP (max 3 per 10 minutes).
     *
     * @return array{allowed: bool, seconds: int}
     */
    public function throttleCheck(string $identifier): array
    {
        $key = "otp-request:{$identifier}";

        if (RateLimiter::tooManyAttempts($key, 3)) {
            return [
                'allowed' => false,
                'seconds' => RateLimiter::availableIn($key),
            ];
        }

        RateLimiter::hit($key, 600);

        return ['allowed' => true, 'seconds' => 0];
    }

    /**
     * Check if a resend is allowed for the given email (max 1 per 60 seconds).
     */
    public function canResend(string $email): bool
    {
        $key = "otp-resend:{$email}";

        if (RateLimiter::tooManyAttempts($key, 1)) {
            return false;
        }

        RateLimiter::hit($key, 60);

        return true;
    }

    public function resolveStudent(string $identifier): ?Student
    {
        return Student::query()
            ->where('email', $identifier)
            ->orWhere('student_id_number', $identifier)
            ->first();
    }

    public function generateOtp(string $email): string
    {
        OtpCode::query()
            ->where('email', $email)
            ->whereNull('verified_at')
            ->whereNull('revoked_at')
            ->update(['revoked_at' => now()]);

        $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        OtpCode::create([
            'email' => $email,
            'code' => Hash::make($code),
            'expires_at' => now()->addMinutes(10),
        ]);

        return $code;
    }

    public function sendOtp(string $email, string $code): void
    {
        Notification::route('mail', $email)
            ->notify(new OtpLoginNotification($code));
    }

    /**
     * @return array{success: bool, message: string}
     */
    public function verifyOtp(string $email, string $code): array
    {
        $otpCode = OtpCode::query()
            ->where('email', $email)
            ->whereNull('verified_at')
            ->whereNull('revoked_at')
            ->latest()
            ->first();

        if (! $otpCode) {
            return ['success' => false, 'message' => 'No OTP found. Please request a new code.'];
        }

        if ($otpCode->isExpired()) {
            return ['success' => false, 'message' => 'This code has expired. Please request a new one.'];
        }

        if ($otpCode->hasExceededAttempts()) {
            return ['success' => false, 'message' => 'Too many failed attempts. Please request a new code.'];
        }

        if (! Hash::check($code, $otpCode->code)) {
            $otpCode->increment('attempts');

            return ['success' => false, 'message' => 'Invalid verification code.'];
        }

        $otpCode->update(['verified_at' => now()]);

        return ['success' => true, 'message' => 'OTP verified successfully.'];
    }

    public function ensureUserExists(Student $student): User
    {
        $user = User::query()->where('email', $student->email)->first();

        if ($user) {
            return $user;
        }

        return User::forceCreate([
            'name' => "{$student->first_name} {$student->last_name}",
            'email' => $student->email,
            'password' => Str::random(64),
            'role' => Role::Student,
            'email_verified_at' => now(),
        ]);
    }
}
