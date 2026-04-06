<?php

namespace App\Livewire;

use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeAuditLog;
use App\Models\Student;
use App\Services\BackfillLabGradesService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class StudentVerification extends Component
{
    public string $token = '';

    public string $step = 'lookup';

    public string $studentIdNumber = '';

    public string $githubUsername = '';

    public string $personalEmail = '';

    public string $courseName = '';

    public string $courseCode = '';

    public string $semesterLabel = '';

    public string $studentName = '';

    public string $studentEmail = '';

    public string $currentGithub = '';

    public string $errorMessage = '';

    public int $backfillCount = 0;

    protected ?int $courseOfferingId = null;

    protected ?int $studentId = null;

    public function mount(string $token): void
    {
        $this->token = $token;

        $offering = CourseOffering::where('verification_token', $token)
            ->with(['course', 'semester.year'])
            ->first();

        if (! $offering || ! $offering->hasValidVerificationToken()) {
            $this->step = 'expired';

            return;
        }

        $this->courseOfferingId = $offering->id;
        $this->courseCode = $offering->course->code;
        $this->courseName = $offering->course->name;
        $this->semesterLabel = ($offering->semester->year->name ?? '').' '.$offering->semester->name;
    }

    public function verifyStudent(): void
    {
        $this->validate([
            'studentIdNumber' => ['required', 'string', 'max:20'],
        ], [
            'studentIdNumber.required' => 'Please enter your student ID number.',
        ]);

        $key = 'verify-student:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->errorMessage = 'Too many attempts. Please wait a moment and try again.';
            $this->step = 'not_found';

            return;
        }
        RateLimiter::hit($key, 60);

        $offering = $this->resolveOffering();
        if (! $offering) {
            $this->step = 'expired';

            return;
        }

        $student = Student::where('student_id_number', trim($this->studentIdNumber))->first();

        if (! $student) {
            $this->errorMessage = "We don't have your registration details. Please make sure you are registered for the course and have access to Moodle. Contact your course lecturer with your student ID, full name, and email address.";
            $this->step = 'not_found';

            return;
        }

        $enrolled = Enrollment::where('student_id', $student->id)
            ->where('course_offering_id', $offering->id)
            ->exists();

        if (! $enrolled) {
            $this->errorMessage = "You are not enrolled in {$this->courseCode}. Please make sure you are registered for the course and have access to Moodle. Contact your course lecturer with your student ID, full name, and email address.";
            $this->step = 'not_found';

            return;
        }

        $this->studentId = $student->id;
        $this->studentName = $student->first_name.' '.$student->last_name;
        $this->studentEmail = $student->email;
        $this->currentGithub = $student->github_username ?? '';
        $this->githubUsername = $student->github_username ?? '';
        $this->personalEmail = $student->personal_email ?? '';
        $this->step = 'found';
    }

    public function updateDetails(): void
    {
        $this->validate([
            'githubUsername' => ['nullable', 'string', 'max:39'],
            'personalEmail' => ['nullable', 'email', 'max:255'],
        ]);

        $offering = $this->resolveOffering();
        if (! $offering) {
            $this->step = 'expired';

            return;
        }

        $student = Student::where('student_id_number', trim($this->studentIdNumber))->first();
        if (! $student) {
            $this->step = 'expired';

            return;
        }

        $username = trim($this->githubUsername);
        $email = trim($this->personalEmail);

        if ($username !== '') {
            try {
                $response = Http::connectTimeout(3)->timeout(5)->get("https://api.github.com/users/{$username}");

                if (! $response->successful()) {
                    $this->addError('githubUsername', 'This GitHub username does not exist.');

                    return;
                }
            } catch (\Throwable) {
                // GitHub API unreachable, allow through
            }

            $taken = Student::where('github_username', $username)
                ->where('id', '!=', $student->id)
                ->exists();

            if ($taken) {
                $this->addError('githubUsername', 'This GitHub username is already linked to another student.');

                return;
            }
        }

        $oldValues = [
            'github_username' => $student->github_username,
            'personal_email' => $student->personal_email,
        ];

        $student->update([
            'github_username' => $username ?: null,
            'personal_email' => $email ?: null,
        ]);

        GradeAuditLog::create([
            'auditable_type' => Student::class,
            'auditable_id' => $student->id,
            'user_id' => null,
            'action' => 'verification_form_update',
            'old_values' => $oldValues,
            'new_values' => [
                'github_username' => $username ?: null,
                'personal_email' => $email ?: null,
            ],
            'ip_address' => request()->ip(),
        ]);

        $this->backfillCount = 0;
        if ($username !== '' && $username !== $oldValues['github_username']) {
            $backfill = app(BackfillLabGradesService::class)->backfillForStudent($student);
            $this->backfillCount = $backfill['grades_created'] ?? 0;
        }

        $this->step = 'updated';
    }

    public function resetLookup(): void
    {
        $this->reset(['studentIdNumber', 'githubUsername', 'personalEmail', 'studentName', 'studentEmail', 'currentGithub', 'errorMessage', 'backfillCount']);
        $this->step = 'lookup';
    }

    public function render()
    {
        return view('livewire.student-verification')
            ->title("{$this->courseCode} - Student Verification");
    }

    protected function resolveOffering(): ?CourseOffering
    {
        $offering = CourseOffering::where('verification_token', $this->token)->first();

        if (! $offering || ! $offering->hasValidVerificationToken()) {
            return null;
        }

        return $offering;
    }
}
