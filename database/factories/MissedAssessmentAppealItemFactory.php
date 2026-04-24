<?php

namespace Database\Factories;

use App\Models\Assessment;
use App\Models\MissedAssessmentAppeal;
use App\Models\MissedAssessmentAppealItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MissedAssessmentAppealItem>
 */
class MissedAssessmentAppealItemFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'missed_assessment_appeal_id' => MissedAssessmentAppeal::factory(),
            'assessment_id' => Assessment::factory(),
            'status' => MissedAssessmentAppealItem::STATUS_PENDING,
            'reviewer_notes' => null,
            'reviewed_at' => null,
        ];
    }
}
