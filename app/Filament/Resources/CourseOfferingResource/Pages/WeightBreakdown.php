<?php

namespace App\Filament\Resources\CourseOfferingResource\Pages;

use App\Filament\Resources\CourseOfferingResource;
use App\Models\Assessment;
use App\Models\CourseOffering;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;

class WeightBreakdown extends Page
{
    protected static string $resource = CourseOfferingResource::class;

    protected string $view = 'filament.resources.course-offering-resource.pages.weight-breakdown';

    public CourseOffering $offering;

    /** @var array<int, array{name: string, type: string, assessments: array<int, array{id: int, name: string, max_raw_score: float, normalized_to: float}>}> */
    public array $groups = [];

    /** @var array<int, string> */
    public array $normalizedValues = [];

    public function mount(int|string $record): void
    {
        $this->offering = CourseOffering::findOrFail($record);
        $this->offering->load(['course', 'assessmentGroups.assessments']);

        $this->loadData();
    }

    protected function loadData(): void
    {
        $this->groups = [];
        $this->normalizedValues = [];

        foreach ($this->offering->assessmentGroups->sortBy('sort_order') as $group) {
            $assessments = [];

            foreach ($group->assessments->sortBy('sort_order') as $assessment) {
                $effectiveNormalized = $assessment->normalized_to ?? $assessment->max_raw_score;

                $assessments[] = [
                    'id' => $assessment->id,
                    'name' => $assessment->name,
                    'max_raw_score' => (float) $assessment->max_raw_score,
                ];

                $this->normalizedValues[$assessment->id] = (string) $effectiveNormalized;
            }

            $this->groups[] = [
                'name' => $group->name,
                'type' => $group->type,
                'assessments' => $assessments,
            ];
        }
    }

    public function save(): void
    {
        foreach ($this->normalizedValues as $assessmentId => $value) {
            Assessment::where('id', $assessmentId)->update([
                'normalized_to' => (float) $value,
            ]);
        }

        Notification::make()
            ->title('Weight breakdown saved successfully!')
            ->success()
            ->send();
    }

    public function getTitle(): string
    {
        return 'Score Normalization';
    }

    public function getSubheading(): ?string
    {
        return 'Configure how raw scores are normalized for grade calculation. For the weight hierarchy overview, use the Weight Overview page.';
    }
}
