<?php

namespace App\Livewire;

use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeAuditLog;
use App\Models\Student;
use App\Models\UsernameDispute;
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

    public string $gender = '';

    public string $errorMessage = '';

    public int $backfillCount = 0;

    public bool $showDisputeOption = false;

    public bool $disputeFiled = false;

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
            $this->errorMessage = "We don't have your registration details. Please make sure you are registered for {$this->courseCode} and have access to Moodle. Contact your course lecturer with your student ID, full name, email address, and course ({$this->courseCode}).";
            $this->step = 'not_found';

            return;
        }

        $enrolled = Enrollment::where('student_id', $student->id)
            ->where('course_offering_id', $offering->id)
            ->exists();

        if (! $enrolled) {
            $this->errorMessage = "You are not enrolled in {$this->courseCode}. Please make sure you are registered for the course and have access to Moodle. Contact your course lecturer with your student ID, full name, email address, and course ({$this->courseCode}).";
            $this->step = 'not_found';

            return;
        }

        $this->studentId = $student->id;
        $this->studentName = $student->first_name.' '.$student->last_name;
        $this->studentEmail = $student->email;
        $this->currentGithub = $student->github_username ?? '';
        $this->githubUsername = $student->github_username ?? '';
        $this->personalEmail = $student->personal_email ?? '';
        $this->gender = $student->gender ?? '';
        $this->step = 'review';
    }

    public function confirmDetails(): void
    {
        $missing = [];

        if (! $this->currentGithub) {
            $missing[] = 'GitHub username';
        }

        if (! $this->gender) {
            $missing[] = 'gender';
        }

        if ($missing) {
            $this->errorMessage = 'Please update your '.implode(' and ', $missing).' before confirming.';

            return;
        }

        $this->resetLookup();
    }

    public function proceedToEdit(): void
    {
        $this->errorMessage = '';
        $this->step = 'found';
    }

    public function updateDetails(): void
    {
        $this->validate([
            'githubUsername' => ['nullable', 'string', 'max:39'],
            'personalEmail' => ['nullable', 'email', 'max:255'],
            'gender' => ['nullable', 'string', 'in:Male,Female'],
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
        $gender = $this->gender ?: null;

        $githubConflict = false;

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
                $this->showDisputeOption = true;
                $githubConflict = true;
            } else {
                $this->showDisputeOption = false;
            }
        }

        $oldValues = [
            'github_username' => $student->github_username,
            'personal_email' => $student->personal_email,
            'gender' => $student->gender,
        ];

        $updates = [
            'personal_email' => $email ?: null,
            'gender' => $gender,
        ];

        // Only update GitHub if there's no conflict
        if (! $githubConflict) {
            $updates['github_username'] = $username ?: null;
        }

        $student->update($updates);

        GradeAuditLog::create([
            'auditable_type' => Student::class,
            'auditable_id' => $student->id,
            'user_id' => null,
            'action' => 'verification_form_update',
            'old_values' => $oldValues,
            'new_values' => $updates,
            'ip_address' => request()->ip(),
        ]);

        $this->backfillCount = 0;
        if (! $githubConflict && $username !== '' && $username !== $oldValues['github_username']) {
            $backfill = app(BackfillLabGradesService::class)->backfillForStudent($student);
            $this->backfillCount = $backfill['grades_created'] ?? 0;
        }

        if (! $githubConflict) {
            $this->step = 'updated';
        }
    }

    public function fileDispute(): void
    {
        $username = trim($this->githubUsername);

        if ($username === '') {
            return;
        }

        $offering = $this->resolveOffering();
        if (! $offering) {
            $this->step = 'expired';

            return;
        }

        $student = Student::where('student_id_number', trim($this->studentIdNumber))->first();
        if (! $student) {
            return;
        }

        $currentHolder = Student::where('github_username', $username)->first();
        if (! $currentHolder) {
            return;
        }

        // Prevent duplicate disputes
        $existingDispute = UsernameDispute::where('claimant_student_id', $student->id)
            ->where('github_username', $username)
            ->where('status', 'pending')
            ->exists();

        if ($existingDispute) {
            $this->disputeFiled = true;
            $this->showDisputeOption = false;

            return;
        }

        UsernameDispute::create([
            'claimant_student_id' => $student->id,
            'current_holder_student_id' => $currentHolder->id,
            'github_username' => $username,
            'course_offering_id' => $offering->id,
            'status' => 'pending',
            'ip_address' => request()->ip(),
        ]);

        $this->disputeFiled = true;
        $this->showDisputeOption = false;
    }

    public function resetLookup(): void
    {
        $this->reset(['studentIdNumber', 'githubUsername', 'personalEmail', 'gender', 'studentName', 'studentEmail', 'currentGithub', 'errorMessage', 'backfillCount', 'showDisputeOption', 'disputeFiled']);
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
