<?php

namespace App\Livewire;

use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\Student;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;

class PublicGrades extends Component
{
    public string $token = '';

    public string $step = 'lookup';

    public string $studentIdNumber = '';

    public string $courseName = '';

    public string $courseCode = '';

    public string $semesterLabel = '';

    public string $studentName = '';

    public string $studentEmail = '';

    public string $githubUsername = '';

    public string $errorMessage = '';

    /** @var array<int, array{group_name: string, assessments: array<int, array{name: string, raw_score: float|null, max_score: float, is_excused: bool}>}> */
    public array $gradeData = [];

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

        $this->courseCode = $offering->course->code;
        $this->courseName = $offering->course->name;
        $this->semesterLabel = ($offering->semester->year->name ?? '').' '.$offering->semester->name;
    }

    public function viewGrades(): void
    {
        $this->validate([
            'studentIdNumber' => ['required', 'string', 'max:20'],
        ], [
            'studentIdNumber.required' => 'Please enter your student ID number.',
        ]);

        $key = 'view-grades:'.request()->ip();
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

        $enrollment = Enrollment::where('student_id', $student->id)
            ->where('course_offering_id', $offering->id)
            ->with([
                'courseOffering.assessmentGroups.assessments',
                'gradeResults.assessment',
            ])
            ->first();

        if (! $enrollment) {
            $this->errorMessage = "You are not enrolled in {$this->courseCode}. Please make sure you are registered for the course and have access to Moodle. Contact your course lecturer with your student ID, full name, email address, and course ({$this->courseCode}).";
            $this->step = 'not_found';

            return;
        }

        $this->studentName = $student->first_name.' '.$student->last_name;
        $this->studentEmail = $student->email;
        $this->githubUsername = $student->github_username ?? '';

        $this->gradeData = $this->buildGradeData($enrollment);
        $this->step = 'found';
    }

    public function resetLookup(): void
    {
        $this->reset(['studentIdNumber', 'studentName', 'studentEmail', 'githubUsername', 'errorMessage', 'gradeData']);
        $this->step = 'lookup';
    }

    public function render()
    {
        return view('livewire.public-grades')
            ->title("{$this->courseCode} - Grades");
    }

    protected function resolveOffering(): ?CourseOffering
    {
        $offering = CourseOffering::where('verification_token', $this->token)->first();

        if (! $offering || ! $offering->hasValidVerificationToken()) {
            return null;
        }

        return $offering;
    }

    /**
     * @return array<int, array{group_name: string, assessments: array<int, array{name: string, raw_score: float|null, max_score: float, is_excused: bool}>}>
     */
    protected function buildGradeData(Enrollment $enrollment): array
    {
        $gradeResultsByAssessment = $enrollment->gradeResults->keyBy('assessment_id');

        $groups = $enrollment->courseOffering->assessmentGroups
            ->where('type', 'ca')
            ->sortBy('sort_order');

        $data = [];

        foreach ($groups as $group) {
            $assessments = [];

            foreach ($group->assessments->sortBy('sort_order') as $assessment) {
                $result = $gradeResultsByAssessment->get($assessment->id);

                $assessments[] = [
                    'name' => $assessment->name,
                    'raw_score' => $result?->is_excused ? null : ($result?->raw_score !== null ? (float) $result->raw_score : null),
                    'max_score' => (float) $assessment->max_raw_score,
                    'is_excused' => (bool) $result?->is_excused,
                ];
            }

            if (count($assessments) > 0) {
                $data[] = [
                    'group_name' => $group->name,
                    'assessments' => $assessments,
                ];
            }
        }

        return $data;
    }
}
