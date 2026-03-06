<?php

namespace Database\Seeders;

use App\Models\GradingScheme;
use App\Models\GradingSchemeLevel;
use Illuminate\Database\Seeder;

class GradingSchemeSeeder extends Seeder
{
    public function run(): void
    {
        $scheme = GradingScheme::firstOrCreate(
            ['name' => 'UNZA Default Scale'],
            [
                'is_default' => true,
                'rounding_rule' => 'round',
                'decimal_places' => 0,
                'rounding_precision' => 0,
                'boundary_behavior' => 'inclusive_lower',
            ]
        );

        // Remove old levels that no longer apply (F grade)
        GradingSchemeLevel::query()
            ->where('grading_scheme_id', $scheme->id)
            ->whereNotIn('letter', ['A+', 'A', 'B+', 'B', 'C+', 'C', 'D+', 'D'])
            ->delete();

        $levels = [
            ['letter' => 'A+', 'min_mark' => 90, 'max_mark' => 100, 'grade_points' => 4.0, 'sort_order' => 1],
            ['letter' => 'A', 'min_mark' => 80, 'max_mark' => 89, 'grade_points' => 4.0, 'sort_order' => 2],
            ['letter' => 'B+', 'min_mark' => 70, 'max_mark' => 79, 'grade_points' => 3.5, 'sort_order' => 3],
            ['letter' => 'B', 'min_mark' => 60, 'max_mark' => 69, 'grade_points' => 3.0, 'sort_order' => 4],
            ['letter' => 'C+', 'min_mark' => 50, 'max_mark' => 59, 'grade_points' => 2.5, 'sort_order' => 5],
            ['letter' => 'C', 'min_mark' => 40, 'max_mark' => 49, 'grade_points' => 2.0, 'sort_order' => 6],
            ['letter' => 'D+', 'min_mark' => 35, 'max_mark' => 39, 'grade_points' => 1.5, 'sort_order' => 7],
            ['letter' => 'D', 'min_mark' => 0, 'max_mark' => 34, 'grade_points' => 1.0, 'sort_order' => 8],
        ];

        foreach ($levels as $level) {
            GradingSchemeLevel::updateOrCreate(
                [
                    'grading_scheme_id' => $scheme->id,
                    'letter' => $level['letter'],
                ],
                $level
            );
        }
    }
}
