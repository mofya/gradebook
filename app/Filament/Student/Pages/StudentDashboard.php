<?php

namespace App\Filament\Student\Pages;

use App\Models\Enrollment;
use App\Models\GradeQuery;
use App\Models\Student;
use App\Services\GradingService;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard as BaseDashboard;

class StudentDashboard extends BaseDashboard
{
    protected string $view = 'filament.student.pages.student-dashboard';

    public string $githubUsername = '';

    public bool $editingGithub = false;

    public function mount(): void
    {
        $student = $this->getStudent();

        $this->githubUsername = $student?->github_username ?? '';
    }

    public function toggleEditGithub(): void
    {
        $this->editingGithub = ! $this->editingGithub;

        if ($this->editingGithub) {
            $student = $this->getStudent();
            $this->githubUsername = $student?->github_username ?? '';
        }
    }

    public function saveGithubUsername(): void
    {
        $student = $this->getStudent();

        if (! $student) {
            return;
        }

        $trimmed = trim($this->githubUsername);

        if ($trimmed === '') {
            $student->update(['github_username' => null]);
            $this->editingGithub = false;

            Notification::make()->title('GitHub username removed.')->success()->send();

            return;
        }

        // Basic format validation
        if (! preg_match('/^[a-zA-Z0-9](?:[a-zA-Z0-9]|-(?=[a-zA-Z0-9])){0,38}$/', $trimmed)) {
            Notification::make()
                ->title('Invalid GitHub username format.')
                ->body('GitHub usernames can only contain alphanumeric characters and hyphens, cannot start or end with a hyphen, and are max 39 characters.')
                ->danger()
                ->send();

            return;
        }

        // Uniqueness check
        $taken = Student::where('github_username', $trimmed)
            ->where('id', '!=', $student->id)
            ->exists();

        if ($taken) {
            Notification::make()
                ->title('This GitHub username is already linked to another student.')
                ->danger()
                ->send();

            return;
        }

        $student->update(['github_username' => $trimmed]);
        $this->githubUsername = $trimmed;
        $this->editingGithub = false;

        Notification::make()->title('GitHub username updated.')->success()->send();
    }

    public function getViewData(): array
    {
        $user = auth()->user();
        $gradingService = app(GradingService::class);

        $student = $this->getStudent();

        if (! $student) {
            return [
                'student' => null,
                'courseCards' => collect(),
                'stats' => [],
                'openQueries' => 0,
            ];
        }

        $enrollments = Enrollment::query()
            ->where('student_id', $student->id)
            ->with([
                'courseOffering.course',
                'courseOffering.semester.year',
                'courseOffering.gradingScheme.levels',
            ])
            ->get();

        $openQueries = GradeQuery::where('student_id', $student->id)
            ->whereNotIn('status', ['resolved', 'rejected'])
            ->count();

        $courseCards = $enrollments->map(function (Enrollment $enrollment) use ($gradingService) {
            $offering = $enrollment->courseOffering;
            $caWeight = (float) ($offering->ca_weight ?? 50);
            $caTotal = $enrollment->ca_total !== null ? (float) $enrollment->ca_total : null;
            $weightedCa = $caTotal !== null ? round($caTotal * $caWeight / 100, 1) : null;
            $scheme = $offering->gradingScheme;
            $caGrade = $caTotal !== null
                ? $gradingService->getLetterGradeFromScheme($caTotal, $scheme)
                : null;

            $semester = $offering->semester;
            $semesterLabel = ($semester->year->name ?? '').' '.$semester->name;

            return [
                'course_code' => $offering->course->code,
                'course_name' => $offering->course->name,
                'semester' => $semesterLabel,
                'status' => $enrollment->status,
                'ca_weight' => $caWeight,
                'ca_total' => $caTotal,
                'weighted_ca' => $weightedCa,
                'ca_grade' => $caGrade,
            ];
        });

        $gradedCourses = $courseCards->filter(fn ($c) => $c['ca_total'] !== null);
        $overallAverage = $gradedCourses->isNotEmpty()
            ? round($gradedCourses->avg('ca_total'), 1)
            : null;

        return [
            'student' => $student,
            'courseCards' => $courseCards,
            'openQueries' => $openQueries,
            'stats' => [
                'enrolled_courses' => $courseCards->count(),
                'graded_courses' => $gradedCourses->count(),
                'overall_average' => $overallAverage,
            ],
        ];
    }

    protected function getStudent(): ?Student
    {
        return Student::where('email', auth()->user()->email)->first();
    }
}
