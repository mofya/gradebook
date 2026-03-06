<?php

namespace App\Observers;

use App\Models\Enrollment;
use App\Services\OtpAuthService;

class EnrollmentObserver
{
    public function created(Enrollment $enrollment): void
    {
        $enrollment->loadMissing('student');

        if ($enrollment->student) {
            app(OtpAuthService::class)->ensureUserExists($enrollment->student);
        }
    }
}
