<?php

namespace App\Livewire;

use App\Models\CourseOffering;
use Livewire\Component;

class PublicClassGrades extends Component
{
    public string $token = '';

    public string $step = 'loading';

    public string $courseCode = '';

    public string $courseName = '';

    public string $semesterLabel = '';

    public string $section = '';

    public string $sortColumn = 'student_id_number';

    public string $sortDirection = 'asc';

    public string $search = '';

    /** @var array<int, array{name: string, id: int}> */
    public array $assessmentColumns = [];

    /** @var array<int, array{student_id_number: string, github_username: string|null, gender: string|null, scores: array<int, array{raw_score: float|null, max_score: float, is_excused: bool}>}> */
    public array $students = [];

    /** @var array<int, array{student_id_number: string, github_username: string|null, gender: string|null, scores: array<int, array{raw_score: float|null, max_score: float, is_excused: bool}>}> */
    public array $allStudents = [];

    public function mount(string $token): void
    {
        $this->token = $token;

        $offering = CourseOffering::where('public_grade_token', $token)
            ->with(['course', 'semester.year'])
            ->first();

        if (! $offering || ! $offering->hasValidPublicGradeToken()) {
            $this->step = 'expired';

            return;
        }

        $this->courseCode = $offering->course->code;
        $this->courseName = $offering->course->name;
        $this->semesterLabel = ($offering->semester->year->name ?? '').' '.$offering->semester->name;
        $this->section = $offering->section ?? '';

        $this->loadGradeData($offering);
        $this->step = 'loaded';
    }

    public function updatedSearch(): void
    {
        $this->applyFiltering();
        $this->applySorting();
    }

    public function sort(string $column): void
    {
        if ($this->sortColumn === $column) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortColumn = $column;
            $this->sortDirection = 'asc';
        }

        $this->applySorting();
    }

    public function render()
    {
        return view('livewire.public-class-grades')
            ->title("{$this->courseCode} - Class Grades");
    }

    protected function loadGradeData(CourseOffering $offering): void
    {
        $offering->load([
            'assessmentGroups' => fn ($q) => $q->where('type', 'ca')->orderBy('sort_order'),
            'assessmentGroups.assessments' => fn ($q) => $q->orderBy('sort_order'),
            'enrollments.student',
            'enrollments.gradeResults',
        ]);

        $this->assessmentColumns = [];
        foreach ($offering->assessmentGroups as $group) {
            foreach ($group->assessments as $assessment) {
                $this->assessmentColumns[] = [
                    'name' => $assessment->name,
                    'id' => $assessment->id,
                    'max_score' => (float) $assessment->max_raw_score,
                ];
            }
        }

        $this->allStudents = [];
        foreach ($offering->enrollments as $enrollment) {
            $gradesByAssessment = $enrollment->gradeResults->keyBy('assessment_id');

            $scores = [];
            foreach ($this->assessmentColumns as $col) {
                $result = $gradesByAssessment->get($col['id']);
                $scores[] = [
                    'raw_score' => $result?->is_excused ? null : ($result?->raw_score !== null ? (float) $result->raw_score : null),
                    'max_score' => $col['max_score'],
                    'is_excused' => (bool) $result?->is_excused,
                ];
            }

            $this->allStudents[] = [
                'student_id_number' => $enrollment->student->student_id_number ?? '',
                'github_username' => $enrollment->student->github_username,
                'gender' => $enrollment->student->gender,
                'scores' => $scores,
            ];
        }

        $this->applyFiltering();
        $this->applySorting();
    }

    protected function applyFiltering(): void
    {
        if ($this->search === '') {
            $this->students = $this->allStudents;

            return;
        }

        $term = mb_strtolower($this->search);

        $this->students = array_values(array_filter($this->allStudents, function (array $student) use ($term): bool {
            return str_contains(mb_strtolower($student['student_id_number']), $term)
                || str_contains(mb_strtolower($student['github_username'] ?? ''), $term);
        }));
    }

    protected function applySorting(): void
    {
        $column = $this->sortColumn;
        $direction = $this->sortDirection;

        usort($this->students, function (array $a, array $b) use ($column, $direction): int {
            $valueA = $a[$column] ?? '';
            $valueB = $b[$column] ?? '';

            $result = strnatcasecmp((string) $valueA, (string) $valueB);

            return $direction === 'asc' ? $result : -$result;
        });
    }
}
