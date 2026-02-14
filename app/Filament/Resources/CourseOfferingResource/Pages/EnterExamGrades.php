<?php

namespace App\Filament\Resources\CourseOfferingResource\Pages;

use App\Filament\Resources\CourseOfferingResource;
use App\Models\CourseOffering;
use App\Models\GradeResult;
use App\Models\SubsectionScore;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class EnterExamGrades extends Page
{
    protected static string $resource = CourseOfferingResource::class;

    protected string $view = 'filament.resources.course-offering-resource.pages.enter-exam-grades';

    public CourseOffering $offering;

    /** @var array<int, array{student_name: string, raw_score: float|null}> */
    public array $grades = [];

    /** @var array<int, array<int, float|null>> */
    public array $subsectionGrades = [];

    public ?int $selectedAssessmentId = null;

    public bool $hasSubsections = false;

    /** @var array<int, array{id: int, name: string, max_score: float}> */
    public array $subsections = [];

    public function mount(int|string $record): void
    {
        $this->offering = CourseOffering::findOrFail($record);
        $this->offering->load(['assessmentGroups.assessments.subsections', 'enrollments.student']);
    }

    public function getExamAssessmentsProperty(): \Illuminate\Support\Collection
    {
        return $this->offering->assessmentGroups
            ->where('type', 'exam')
            ->flatMap->assessments;
    }

    public function updatedSelectedAssessmentId(): void
    {
        $this->loadGrades();
    }

    public function loadGrades(): void
    {
        if (! $this->selectedAssessmentId) {
            $this->grades = [];
            $this->subsectionGrades = [];
            $this->hasSubsections = false;
            $this->subsections = [];

            return;
        }

        $assessment = $this->examAssessments->firstWhere('id', $this->selectedAssessmentId);
        $this->hasSubsections = $assessment && $assessment->has_subsections && $assessment->subsections->isNotEmpty();

        if ($this->hasSubsections) {
            $this->subsections = $assessment->subsections->map(fn ($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'max_score' => (float) $s->max_score,
            ])->toArray();
        } else {
            $this->subsections = [];
        }

        $existingResults = GradeResult::query()
            ->where('assessment_id', $this->selectedAssessmentId)
            ->whereIn('enrollment_id', $this->offering->enrollments->pluck('id'))
            ->with('subsectionScores')
            ->get()
            ->keyBy('enrollment_id');

        $this->grades = $this->offering->enrollments->mapWithKeys(function ($enrollment) use ($existingResults) {
            $result = $existingResults->get($enrollment->id);

            return [$enrollment->id => [
                'student_name' => $enrollment->student->first_name.' '.$enrollment->student->last_name,
                'student_id_number' => $enrollment->student->student_id_number,
                'raw_score' => $result?->raw_score,
            ]];
        })->toArray();

        if ($this->hasSubsections) {
            $this->subsectionGrades = $this->offering->enrollments->mapWithKeys(function ($enrollment) use ($existingResults) {
                $result = $existingResults->get($enrollment->id);
                $scores = $result ? $result->subsectionScores->pluck('score', 'assessment_subsection_id') : collect();

                $subsectionData = [];
                foreach ($this->subsections as $sub) {
                    $subsectionData[$sub['id']] = $scores->get($sub['id']);
                }

                return [$enrollment->id => $subsectionData];
            })->toArray();
        } else {
            $this->subsectionGrades = [];
        }
    }

    public function submit(): void
    {
        if (! $this->selectedAssessmentId) {
            return;
        }

        foreach ($this->grades as $enrollmentId => $data) {
            if ($this->hasSubsections) {
                $hasAnySubsection = collect($this->subsectionGrades[$enrollmentId] ?? [])
                    ->filter(fn ($v) => $v !== null && $v !== '')->isNotEmpty();

                if (! $hasAnySubsection) {
                    continue;
                }

                $result = GradeResult::updateOrCreate(
                    [
                        'enrollment_id' => $enrollmentId,
                        'assessment_id' => $this->selectedAssessmentId,
                    ],
                    ['source' => 'manual']
                );

                foreach ($this->subsectionGrades[$enrollmentId] ?? [] as $subsectionId => $score) {
                    if ($score === null || $score === '') {
                        continue;
                    }

                    SubsectionScore::updateOrCreate(
                        [
                            'grade_result_id' => $result->id,
                            'assessment_subsection_id' => $subsectionId,
                        ],
                        ['score' => $score]
                    );
                }

                $total = $result->calculateFromSubsections();
                $normalized = $result->fresh()->calculateNormalizedScore();
                if ($normalized !== null) {
                    $result->update(['normalized_score' => $normalized]);
                }
            } else {
                if ($data['raw_score'] === null || $data['raw_score'] === '') {
                    continue;
                }

                $result = GradeResult::updateOrCreate(
                    [
                        'enrollment_id' => $enrollmentId,
                        'assessment_id' => $this->selectedAssessmentId,
                    ],
                    [
                        'raw_score' => $data['raw_score'],
                        'source' => 'manual',
                    ]
                );

                $normalized = $result->calculateNormalizedScore();
                if ($normalized !== null) {
                    $result->update(['normalized_score' => $normalized]);
                }
            }
        }

        Notification::make()
            ->title('Exam grades saved successfully!')
            ->success()
            ->send();
    }

    public function getTitle(): string
    {
        return 'Enter Exam Grades';
    }
}
