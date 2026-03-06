<?php

namespace App\Filament\Resources\CourseOfferingResource\Pages;

use App\Filament\Resources\CourseOfferingResource;
use App\Models\CourseOffering;
use Filament\Resources\Pages\Page;

class WeightOverview extends Page
{
    protected static string $resource = CourseOfferingResource::class;

    protected string $view = 'filament.resources.course-offering-resource.pages.weight-overview';

    public CourseOffering $offering;

    /** @var array<int, array{name: string, type: string, weight_percentage: float, assessments: array<int, array{name: string, weight: float}>}> */
    public array $groups = [];

    public function mount(int|string $record): void
    {
        $this->offering = CourseOffering::with(['course', 'assessmentGroups.assessments'])->findOrFail($record);
        $this->loadGroups();
    }

    protected function loadGroups(): void
    {
        $this->groups = $this->offering->assessmentGroups
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

    public function getTitle(): string
    {
        return 'Weight Overview';
    }

    public function getSubheading(): ?string
    {
        $course = $this->offering->course;

        return $course ? "{$course->code} — {$course->name}" : null;
    }
}
