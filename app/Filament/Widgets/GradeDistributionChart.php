<?php

namespace App\Filament\Widgets;

use App\Models\Enrollment;
use Filament\Widgets\ChartWidget;

class GradeDistributionChart extends ChartWidget
{
    protected ?string $heading = 'Grade Distribution';

    protected ?string $pollingInterval = null;

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $grades = Enrollment::query()
            ->whereNotNull('final_grade')
            ->selectRaw('final_grade, count(*) as count')
            ->groupBy('final_grade')
            ->orderByRaw("CASE final_grade
                WHEN 'A+' THEN 1 WHEN 'A' THEN 2
                WHEN 'B+' THEN 3 WHEN 'B' THEN 4
                WHEN 'C+' THEN 5 WHEN 'C' THEN 6
                WHEN 'D+' THEN 7 WHEN 'D' THEN 8
                ELSE 9 END")
            ->pluck('count', 'final_grade');

        return [
            'datasets' => [
                [
                    'label' => 'Students',
                    'data' => $grades->values()->toArray(),
                    'backgroundColor' => [
                        '#059669', '#10b981',
                        '#0284c7', '#38bdf8',
                        '#d97706', '#fbbf24',
                        '#dc2626', '#f87171',
                    ],
                ],
            ],
            'labels' => $grades->keys()->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }
}
