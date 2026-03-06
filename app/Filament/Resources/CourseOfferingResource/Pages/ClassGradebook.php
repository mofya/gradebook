<?php

namespace App\Filament\Resources\CourseOfferingResource\Pages;

use App\Filament\Resources\CourseOfferingResource;
use App\Models\CourseOffering;
use Filament\Resources\Pages\Page;

class ClassGradebook extends Page
{
    protected static string $resource = CourseOfferingResource::class;

    protected string $view = 'filament.resources.course-offering-resource.pages.class-gradebook';

    public CourseOffering $offering;

    /** @var array<int, array{student_id_number: string, first_name: string, last_name: string, ca_total: float|null, exam_score: float|null, final_total: float|null, final_grade: string|null, grade_points: float|null}> */
    public array $students = [];

    /** @var array<int, array{id: int, name: string, group_name: string, max_raw_score: float|null}> */
    public array $assessments = [];

    /** @var array<int, array<int, array{raw_score: float|null, normalized_score: float|null, is_excused: bool}>> */
    public array $gradeMatrix = [];

    public string $search = '';

    public function mount(int|string $record): void
    {
        $this->offering = CourseOffering::findOrFail($record);
        $this->loadData();
    }

    public function updatedSearch(): void
    {
        $this->loadData();
    }

    protected function loadData(): void
    {
        $this->offering->load([
            'assessmentGroups.assessments',
            'enrollments.student',
            'enrollments.gradeResults',
        ]);

        // Build flat assessment list ordered by group sort_order, then assessment sort_order
        $this->assessments = $this->offering->assessmentGroups
            ->sortBy('sort_order')
            ->flatMap(function ($group) {
                return $group->assessments->sortBy('sort_order')->map(fn ($assessment) => [
                    'id' => $assessment->id,
                    'name' => $assessment->name,
                    'group_name' => $group->name,
                    'max_raw_score' => $assessment->max_raw_score,
                ]);
            })
            ->values()
            ->toArray();

        // Filter enrollments by search
        $enrollments = $this->offering->enrollments;

        if ($this->search !== '') {
            $searchLower = mb_strtolower($this->search);
            $enrollments = $enrollments->filter(function ($enrollment) use ($searchLower) {
                $student = $enrollment->student;

                return str_contains(mb_strtolower($student->student_id_number ?? ''), $searchLower)
                    || str_contains(mb_strtolower($student->first_name ?? ''), $searchLower)
                    || str_contains(mb_strtolower($student->last_name ?? ''), $searchLower);
            });
        }

        $enrollments = $enrollments->sortBy(fn ($e) => $e->student->last_name.' '.$e->student->first_name);

        // Build students array and grade matrix
        $this->students = [];
        $this->gradeMatrix = [];

        foreach ($enrollments as $enrollment) {
            $this->students[$enrollment->id] = [
                'student_id_number' => $enrollment->student->student_id_number,
                'first_name' => $enrollment->student->first_name,
                'last_name' => $enrollment->student->last_name,
                'ca_total' => $enrollment->ca_total,
                'exam_score' => $enrollment->exam_score,
                'final_total' => $enrollment->final_total,
                'final_grade' => $enrollment->final_grade,
                'grade_points' => $enrollment->grade_points,
            ];

            $resultsByAssessment = $enrollment->gradeResults->keyBy('assessment_id');

            $this->gradeMatrix[$enrollment->id] = [];

            foreach ($this->assessments as $assessment) {
                $result = $resultsByAssessment->get($assessment['id']);
                $this->gradeMatrix[$enrollment->id][$assessment['id']] = [
                    'raw_score' => $result?->raw_score,
                    'normalized_score' => $result?->normalized_score,
                    'is_excused' => (bool) ($result?->is_excused),
                ];
            }
        }
    }

    public function getTitle(): string
    {
        return 'Class Gradebook';
    }
}
