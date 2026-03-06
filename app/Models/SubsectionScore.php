<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubsectionScore extends Model
{
    /** @use HasFactory<\Database\Factories\SubsectionScoreFactory> */
    use HasFactory;

    protected $fillable = [
        'grade_result_id',
        'assessment_subsection_id',
        'score',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
        ];
    }

    public function gradeResult(): BelongsTo
    {
        return $this->belongsTo(GradeResult::class);
    }

    public function assessmentSubsection(): BelongsTo
    {
        return $this->belongsTo(AssessmentSubsection::class);
    }
}
