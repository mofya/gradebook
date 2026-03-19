<?php

namespace App\Filament\Student\Pages;

use App\Models\Enrollment;
use App\Models\GradeQuery;
use App\Models\GradeQueryMessage;
use App\Models\Student;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;

class GradeQueries extends Page
{
    protected string $view = 'filament.student.pages.grade-queries';

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $title = 'Grade Queries';

    protected static ?int $navigationSort = 3;

    public ?int $selectedEnrollmentId = null;

    public ?int $selectedAssessmentId = null;

    public string $querySubject = '';

    public string $queryBody = '';

    public string $replyBody = '';

    public ?int $replyingToQueryId = null;

    public bool $showCreateForm = false;

    public function getViewData(): array
    {
        $user = auth()->user();

        $student = Student::query()
            ->where('email', $user->email)
            ->first();

        if (! $student) {
            return ['queries' => collect(), 'student' => null, 'enrollments' => collect()];
        }

        $queries = GradeQuery::query()
            ->where('student_id', $student->id)
            ->with(['enrollment.courseOffering.course', 'assessment', 'messages.user'])
            ->latest()
            ->get();

        $enrollments = Enrollment::query()
            ->where('student_id', $student->id)
            ->with(['courseOffering.course', 'courseOffering.assessmentGroups.assessments'])
            ->get();

        return [
            'student' => $student,
            'queries' => $queries,
            'enrollments' => $enrollments,
        ];
    }

    public function toggleCreateForm(): void
    {
        $this->showCreateForm = ! $this->showCreateForm;
    }

    public function getAssessmentsForEnrollmentProperty(): Collection
    {
        if (! $this->selectedEnrollmentId) {
            return collect();
        }

        $enrollment = Enrollment::with('courseOffering.assessmentGroups.assessments')
            ->find($this->selectedEnrollmentId);

        if (! $enrollment) {
            return collect();
        }

        return $enrollment->courseOffering->assessmentGroups->flatMap->assessments;
    }

    public function submitQuery(): void
    {
        $this->validate([
            'selectedEnrollmentId' => ['required', 'exists:enrollments,id'],
            'querySubject' => ['required', 'string', 'min:3', 'max:255'],
            'queryBody' => ['required', 'string', 'min:10'],
        ], [
            'selectedEnrollmentId.required' => 'Please select a course.',
            'querySubject.required' => 'A subject is required.',
            'querySubject.min' => 'Subject must be at least 3 characters.',
            'queryBody.required' => 'A message is required.',
            'queryBody.min' => 'Message must be at least 10 characters.',
        ]);

        $user = auth()->user();
        $student = Student::where('email', $user->email)->first();

        if (! $student) {
            return;
        }

        GradeQuery::create([
            'student_id' => $student->id,
            'enrollment_id' => $this->selectedEnrollmentId,
            'assessment_id' => $this->selectedAssessmentId ?: null,
            'subject' => $this->querySubject,
            'status' => 'open',
            'priority' => 'normal',
            'student_message' => $this->queryBody,
        ]);

        $this->reset(['selectedEnrollmentId', 'selectedAssessmentId', 'querySubject', 'queryBody', 'showCreateForm']);

        Notification::make()->title('Grade query submitted successfully.')->success()->send();
    }

    public function startReply(int $queryId): void
    {
        $this->replyingToQueryId = $queryId;
        $this->replyBody = '';
    }

    public function submitReply(): void
    {
        $this->validate([
            'replyBody' => ['required', 'string', 'min:1'],
        ], [
            'replyBody.required' => 'Reply message cannot be empty.',
        ]);

        $user = auth()->user();

        if (! $this->replyingToQueryId) {
            return;
        }

        GradeQueryMessage::create([
            'grade_query_id' => $this->replyingToQueryId,
            'user_id' => $user->id,
            'body' => $this->replyBody,
            'is_internal_note' => false,
        ]);

        $this->reset(['replyingToQueryId', 'replyBody']);

        Notification::make()->title('Reply sent.')->success()->send();
    }
}
