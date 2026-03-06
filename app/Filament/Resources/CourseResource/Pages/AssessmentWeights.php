<?php

namespace App\Filament\Resources\CourseResource\Pages;

use App\Filament\Resources\CourseOfferingResource;
use App\Filament\Resources\CourseResource;
use App\Models\Course;
use App\Models\CourseOffering;
use Filament\Resources\Pages\Page;

class AssessmentWeights extends Page
{
    protected static string $resource = CourseResource::class;

    protected string $view = 'filament.resources.course-resource.pages.assessment-weights';

    public Course $course;

    public ?int $selectedOfferingId = null;

    public ?CourseOffering $selectedOffering = null;

    /** @var array<int, array{id: int, label: string}> */
    public array $offeringOptions = [];

    /** @var array<int, array{name: string, type: string, weight_percentage: float, assessments: array<int, array{name: string, weight: float}>}> */
    public array $groups = [];

    public function mount(int|string $record): void
    {
        $this->course = Course::findOrFail($record);

        $offerings = CourseOffering::where('course_id', $this->course->id)
            ->with('semester')
            ->orderByDesc('created_at')
            ->get();

        $this->offeringOptions = $offerings->map(fn (CourseOffering $o) => [
            'id' => $o->id,
            'label' => ($o->semester?->name ?? 'No Semester').($o->section ? " ({$o->section})" : ''),
        ])->all();

        if ($offerings->isNotEmpty()) {
            $this->selectedOfferingId = $offerings->first()->id;
            $this->loadOffering();
        }
    }

    public function updatedSelectedOfferingId(): void
    {
        $this->loadOffering();
    }

    protected function loadOffering(): void
    {
        if (! $this->selectedOfferingId) {
            $this->selectedOffering = null;
            $this->groups = [];

            return;
        }

        $this->selectedOffering = CourseOffering::with(['course', 'assessmentGroups.assessments'])
            ->findOrFail($this->selectedOfferingId);

        $this->groups = $this->selectedOffering->assessmentGroups
            ->sortBy('sort_order')
            ->map(fn ($group) => [
                'name' => $group->name,
                'type' => $group->type,
                'weight_percentage' => (float) ($group->weight_percentage ?? 0),
                'assessments' => $group->assessments->sortBy('sort_order')->map(fn ($a) => [
                    'name' => $a->name,
                    'weight' => (float) ($a->normalized_to ?? $a->weight ?? 0),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    public function getWeightOverviewUrl(): ?string
    {
        if (! $this->selectedOfferingId) {
            return null;
        }

        return CourseOfferingResource::getUrl('weight-overview', ['record' => $this->selectedOfferingId]);
    }

    public function getTitle(): string
    {
        return 'Assessment Weights';
    }

    public function getSubheading(): ?string
    {
        return "{$this->course->code} — {$this->course->name}";
    }
}
