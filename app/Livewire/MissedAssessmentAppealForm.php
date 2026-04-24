<?php

namespace App\Livewire;

use App\Models\Assessment;
use App\Models\CourseOffering;
use App\Models\Enrollment;
use App\Models\GradeResult;
use App\Models\MissedAssessmentAppeal;
use App\Models\MissedAssessmentAppealItem;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Component;
use Livewire\WithFileUploads;

class MissedAssessmentAppealForm extends Component
{
    use WithFileUploads;

    public string $token = '';

    public string $step = 'lookup';

    public string $studentIdNumber = '';

    public string $courseCode = '';

    public string $courseName = '';

    public string $semesterLabel = '';

    public string $studentName = '';

    public string $studentEmail = '';

    public string $narrative = '';

    public string $otherNotes = '';

    public bool $deanConfirmed = false;

    public $evidenceFile = null;

    public string $errorMessage = '';

    /** @var array<int, int> */
    public array $selectedAssessmentIds = [];

    /** @var array<int, array{id:int, name:string, group:string}> */
    public array $availableAssessments = [];

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

    public function lookupStudent(): void
    {
        $this->validate(
            ['studentIdNumber' => ['required', 'string', 'max:20']],
            ['studentIdNumber.required' => 'Please enter your student ID number.'],
        );

        $key = 'appeal-lookup:'.request()->ip();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->errorMessage = 'Too many attempts. Please wait a moment and try again.';

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
            $this->errorMessage = "We don't have your registration details for {$this->courseCode}. Contact your course lecturer.";

            return;
        }

        $enrolled = Enrollment::where('student_id', $student->id)
            ->where('course_offering_id', $offering->id)
            ->exists();

        if (! $enrolled) {
            $this->errorMessage = "You are not enrolled in {$this->courseCode}.";

            return;
        }

        $this->studentId = $student->id;
        $this->studentName = $student->first_name.' '.$student->last_name;
        $this->studentEmail = $student->email;
        $this->errorMessage = '';
        $this->loadAvailableAssessments($offering, $student->id);

        $existing = MissedAssessmentAppeal::where('course_offering_id', $offering->id)
            ->where('student_id', $student->id)
            ->first();

        if ($existing) {
            $this->narrative = $existing->narrative;
            $this->otherNotes = $existing->other_notes ?? '';
            $this->deanConfirmed = (bool) $existing->dean_confirmed;
            $this->selectedAssessmentIds = $existing->items()->pluck('assessment_id')->all();
        }

        $this->step = 'form';
    }

    public function submit(): void
    {
        $this->validate([
            'selectedAssessmentIds' => ['required', 'array', 'min:1'],
            'selectedAssessmentIds.*' => ['integer'],
            'narrative' => ['required', 'string', 'min:10', 'max:5000'],
            'otherNotes' => ['nullable', 'string', 'max:2000'],
            'deanConfirmed' => ['accepted'],
            'evidenceFile' => ['nullable', 'file', 'max:10240', 'mimes:pdf,png,jpg,jpeg,doc,docx'],
        ], [
            'selectedAssessmentIds.required' => 'Please select at least one assessment you missed.',
            'narrative.required' => 'Please explain why you missed these assessments.',
            'deanConfirmed.accepted' => 'Please confirm that you have contacted the Assistant Dean.',
        ]);

        $offering = $this->resolveOffering();
        if (! $offering) {
            $this->step = 'expired';

            return;
        }

        // Re-derive student each request — protected properties don't round-trip through Livewire.
        $student = Student::where('student_id_number', trim($this->studentIdNumber))->first();
        $enrolled = $student
            ? Enrollment::where('student_id', $student->id)->where('course_offering_id', $offering->id)->exists()
            : false;

        if (! $student || ! $enrolled) {
            $this->step = 'expired';

            return;
        }

        $this->studentId = $student->id;
        $this->loadAvailableAssessments($offering, $student->id);

        // Ensure all selected assessments belong to this offering and are ungraded for this student.
        $validIds = collect($this->availableAssessments)->pluck('id')->all();
        $picked = array_values(array_intersect($this->selectedAssessmentIds, $validIds));
        if ($picked === []) {
            $this->errorMessage = 'The selected assessments are no longer eligible for appeal.';

            return;
        }

        $evidencePath = null;
        if ($this->evidenceFile) {
            $evidencePath = $this->evidenceFile->store('appeal-evidence', 'local');
        }

        DB::transaction(function () use ($offering, $picked, $evidencePath): void {
            $appeal = MissedAssessmentAppeal::updateOrCreate(
                [
                    'course_offering_id' => $offering->id,
                    'student_id' => $this->studentId,
                ],
                [
                    'narrative' => $this->narrative,
                    'other_notes' => $this->otherNotes ?: null,
                    'dean_confirmed' => $this->deanConfirmed,
                    'evidence_path' => $evidencePath ?? MissedAssessmentAppeal::where('course_offering_id', $offering->id)
                        ->where('student_id', $this->studentId)
                        ->value('evidence_path'),
                    'status' => MissedAssessmentAppeal::STATUS_PENDING,
                    'submitted_at' => now(),
                ],
            );

            // Replace items: remove ones no longer selected, add new ones.
            $existingIds = $appeal->items()->pluck('assessment_id')->all();
            $toRemove = array_diff($existingIds, $picked);
            $toAdd = array_diff($picked, $existingIds);

            if ($toRemove) {
                $appeal->items()->whereIn('assessment_id', $toRemove)->delete();
            }

            foreach ($toAdd as $assessmentId) {
                MissedAssessmentAppealItem::create([
                    'missed_assessment_appeal_id' => $appeal->id,
                    'assessment_id' => $assessmentId,
                    'status' => MissedAssessmentAppealItem::STATUS_PENDING,
                ]);
            }
        });

        $this->step = 'submitted';
    }

    protected function resolveOffering(): ?CourseOffering
    {
        $offering = CourseOffering::where('verification_token', $this->token)->first();
        if (! $offering || ! $offering->hasValidVerificationToken()) {
            return null;
        }

        return $offering;
    }

    protected function loadAvailableAssessments(CourseOffering $offering, int $studentId): void
    {
        $enrollment = Enrollment::where('student_id', $studentId)
            ->where('course_offering_id', $offering->id)
            ->firstOrFail();

        $gradedAssessmentIds = GradeResult::where('enrollment_id', $enrollment->id)
            ->whereNotNull('raw_score')
            ->pluck('assessment_id')
            ->all();

        $assessments = Assessment::whereHas(
            'assessmentGroup',
            fn ($q) => $q->where('course_offering_id', $offering->id),
        )
            ->whereNotIn('id', $gradedAssessmentIds)
            ->with('assessmentGroup')
            ->orderBy('sort_order')
            ->get();

        $this->availableAssessments = $assessments->map(fn (Assessment $a) => [
            'id' => $a->id,
            'name' => $a->name,
            'group' => $a->assessmentGroup->name ?? '',
        ])->all();
    }

    public function render()
    {
        return view('livewire.missed-assessment-appeal-form');
    }
}
