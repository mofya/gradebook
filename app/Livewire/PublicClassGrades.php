<?php

namespace App\Livewire;

use App\Models\AssessmentGroup;
use App\Models\CourseOffering;
use App\Models\GradeResult;
use Illuminate\Support\Collection;
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

    /** @var array<int, array<string, mixed>> */
    public array $students = [];

    /** @var array<int, array<string, mixed>> */
    public array $allStudents = [];

    public float $caWeight = 0.0;

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
            'gradingScheme.levels',
        ]);

        $this->caWeight = (float) ($offering->ca_weight ?? 0);
        $scheme = $offering->gradingScheme;

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

            // Properly weight each CA group by its weight_percentage.
            $caPoints = 0.0;
            foreach ($offering->assessmentGroups as $group) {
                $groupPct = $this->computeGroupPercentage($group, $gradesByAssessment);
                $caPoints += ($groupPct / 100) * (float) $group->weight_percentage;
            }

            $caPoints = round($caPoints, 2);
            $caOutOf100 = $this->caWeight > 0
                ? round(($caPoints / $this->caWeight) * 100, 2)
                : null;

            $caGrade = null;
            if ($caOutOf100 !== null && $scheme) {
                $caGrade = $scheme->getLetterGrade($caOutOf100);
            }

            $this->allStudents[] = [
                'student_id_number' => $enrollment->student->student_id_number ?? '',
                'github_username' => $enrollment->student->github_username,
                'gender' => $enrollment->student->gender,
                'scores' => $scores,
                'ca_points' => $caPoints,
                'ca_out_of_100' => $caOutOf100,
                'ca_grade' => $caGrade,
            ];
        }

        $this->applyFiltering();
        $this->applySorting();
    }

    /**
     * Weighted average (within a group) of assessment percentages.
     * Uses the assessment's `weight` field to break ties between assessments in the same group.
     *
     * @param  Collection<int, GradeResult>  $gradesByAssessment
     */
    protected function computeGroupPercentage(AssessmentGroup $group, Collection $gradesByAssessment): float
    {
        $weightedSum = 0.0;
        $totalWeight = 0.0;

        foreach ($group->assessments as $assessment) {
            $result = $gradesByAssessment->get($assessment->id);
            if (! $result || $result->is_excused || $result->raw_score === null) {
                continue;
            }

            $max = (float) ($assessment->max_raw_score ?: 100);
            if ($max <= 0) {
                continue;
            }

            $percent = ((float) $result->raw_score / $max) * 100;
            $w = (float) ($assessment->weight ?: 1);
            $weightedSum += $percent * $w;
            $totalWeight += $w;
        }

        return $totalWeight > 0 ? $weightedSum / $totalWeight : 0.0;
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
